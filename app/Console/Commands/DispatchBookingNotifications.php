<?php

namespace App\Console\Commands;

use App\Jobs\DeliverBookingNotification;
use App\Jobs\Middleware\UseTenantContext;
use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchBookingNotifications extends Command
{
    protected $signature = 'bookings:dispatch-notifications {--tenant= : Limit to one studio} {--resolve= : Uncertain outbox ID} {--outcome= : delivered or retry, after inbox verification}';

    protected $description = 'Generate due reminders and recover pending local booking notifications';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('M2 delivery is limited to local and testing environments.');

            return self::FAILURE;
        }
        if ($this->option('resolve') && (! $this->option('tenant') || ! in_array($this->option('outcome'), ['delivered', 'retry'], true))) {
            $this->error('Resolution requires --tenant and --outcome=delivered|retry after checking the local inbox.');

            return self::FAILURE;
        }
        foreach (Tenant::query()->where('status', 'active')->when($this->option('tenant'), fn ($query, $id) => $query->whereKey($id))->lazyById(100) as $tenant) {
            (new UseTenantContext($tenant->id))->handle($this, function () use ($tenant): void {
                if ($this->option('resolve')) {
                    $this->resolveUncertain($tenant);

                    return;
                }
                OutboxEvent::query()->where('tenant_id', $tenant->id)->where('status', 'processing')
                    ->where('locked_at', '<=', now()->subMinutes(5))
                    ->update(['status' => 'uncertain', 'last_error_code' => 'worker_interrupted', 'locked_at' => null]);
                $this->reminders($tenant);
                foreach (OutboxEvent::query()->where('tenant_id', $tenant->id)->where('status', 'pending')
                    ->where('attempts', '<', 3)->where('available_at', '<=', now())->lazyById(100) as $event) {
                    DeliverBookingNotification::dispatch($tenant->id, $event->id)->afterCommit();
                    app(TenantContext::class)->setTenant($tenant);
                }
            });
        }

        return self::SUCCESS;
    }

    private function reminders(Tenant $tenant): void
    {
        $ids = Booking::query()->where('tenant_id', $tenant->id)->where('status', 'confirmed')
            ->whereHas('session', fn ($query) => $query->where('status', 'published')
                ->where('starts_at', '<=', now()->addDay())->where('starts_at', '>', now()->addDay()->subMinutes(15)))
            ->select('id')->lazyById(100);
        foreach ($ids as $candidate) {
            DB::transaction(function () use ($tenant, $candidate): void {
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $booking = Booking::query()->where('tenant_id', $tenant->id)->findOrFail($candidate->id);
                $session = $booking->session()->firstOrFail();
                if ($booking->status !== 'confirmed' || $session->status !== 'published'
                    || $booking->confirmed_at->gte($session->starts_at->subDay())
                    || OutboxEvent::query()->where('booking_id', $booking->id)->where('event_type', 'booking.reminder')
                        ->whereIn('status', ['delivered', 'processing', 'uncertain'])->exists()) {
                    return;
                }
                $key = "booking:{$booking->id}:{$session->version}:recipient:{$booking->user_id}:reminder:mail";
                if (! OutboxEvent::query()->where('tenant_id', $tenant->id)->where('deduplication_key', $key)->exists()) {
                    (new OutboxEvent)->forceFill([
                        'booking_id' => $booking->id, 'class_session_id' => $session->id, 'recipient_user_id' => $booking->user_id,
                        'event_type' => 'booking.reminder', 'aggregate_version' => $session->version,
                        'deduplication_key' => $key, 'payload' => ['template_version' => 1], 'available_at' => now(),
                    ])->save();
                }
            }, 3);
        }
    }

    private function resolveUncertain(Tenant $tenant): void
    {
        $count = OutboxEvent::query()->where('tenant_id', $tenant->id)->whereKey($this->option('resolve'))
            ->where('status', 'uncertain')->when($this->option('outcome') === 'retry', fn ($query) => $query->where('attempts', '<', 3))->update([
                'status' => $this->option('outcome') === 'retry' ? 'pending' : 'delivered',
                'processed_at' => $this->option('outcome') === 'retry' ? null : now(),
                'last_error_code' => 'operator_verified_'.$this->option('outcome'),
            ]);
        Log::info('booking_mail_resolution', ['tenant_id' => $tenant->id, 'outbox_id' => $this->option('resolve'), 'outcome' => $this->option('outcome'), 'changed' => $count]);
        $this->info('Resolved rows: '.$count);
    }
}
