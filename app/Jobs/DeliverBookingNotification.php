<?php

namespace App\Jobs;

use App\Actions\PrepareBookingNotification;
use App\Jobs\Middleware\UseTenantContext;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeliverBookingNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $tenantId, public int $eventId) {}

    /** @return list<UseTenantContext> */
    public function middleware(): array
    {
        return [new UseTenantContext($this->tenantId)];
    }

    public function handle(PrepareBookingNotification $prepare): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $mailer = config('mail.default');
        if (! (in_array($mailer, ['array', 'log'], true) && config("mail.mailers.{$mailer}.transport") === $mailer)
            && ! ($mailer === 'smtp' && config('mail.mailers.smtp.transport') === 'smtp'
                && in_array(config('mail.mailers.smtp.host'), ['127.0.0.1', 'localhost', '::1'], true)
                && config('mail.mailers.smtp.url') === null)) {
            return;
        }
        $event = DB::transaction(function () use ($prepare): ?OutboxEvent {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($this->tenantId);
            if ($tenant->status !== 'active') {
                return null;
            }
            $event = OutboxEvent::query()->where('tenant_id', $this->tenantId)->lockForUpdate()->find($this->eventId);
            if (! $event || $event->status !== 'pending' || $event->available_at->isFuture() || $event->attempts >= 3) {
                return null;
            }
            if ($prepare->handle($event) === null) {
                $event->forceFill(['status' => 'failed', 'processed_at' => now(), 'last_error_code' => 'suppressed_ineligible'])->save();

                return null;
            }
            $event->forceFill(['status' => 'processing', 'locked_at' => now(), 'attempts' => $event->attempts + 1, 'last_error_code' => null])->save();

            return $event;
        }, 3);
        if ($event === null) {
            return;
        }
        $notification = $prepare->handle($event);
        if ($notification === null) {
            $event->forceFill(['status' => 'failed', 'processed_at' => now(), 'locked_at' => null, 'last_error_code' => 'suppressed_ineligible'])->save();

            return;
        }
        Log::info('booking_mail_attempt', ['tenant_id' => $this->tenantId, 'outbox_id' => $event->id, 'attempt' => $event->attempts]);
        try {
            $event->recipient()->firstOrFail()->notifyNow($notification);
            $event->forceFill(['status' => 'delivered', 'processed_at' => now(), 'locked_at' => null])->save();
        } catch (Throwable) {
            $event->forceFill(['status' => 'uncertain', 'locked_at' => null, 'last_error_code' => 'transport_or_ack_uncertain'])->save();
        }
        Log::info('booking_mail_result', ['tenant_id' => $this->tenantId, 'outbox_id' => $event->id, 'status' => $event->status]);
    }
}
