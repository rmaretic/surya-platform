<?php

namespace App\Actions;

use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Notifications\BookingNotification;
use App\TenantContext;
use Illuminate\Routing\UrlGenerator;

class PrepareBookingNotification
{
    public function handle(OutboxEvent $event): ?BookingNotification
    {
        $tenant = app(TenantContext::class)->requireTenant();
        if ($event->tenant_id !== $tenant->id || ! Tenant::query()->whereKey($tenant->id)->where('status', 'active')->exists()) {
            return null;
        }
        $booking = $event->booking()->where('tenant_id', $tenant->id)->first();
        $session = $event->session()->where('tenant_id', $tenant->id)->first();
        $recipient = $event->recipient()->where('tenant_id', $tenant->id)->first();
        if (! $booking || ! $session || ! $recipient || ! $recipient->is_active
            || $booking->user_id !== $recipient->id || $booking->class_session_id !== $session->id) {
            return null;
        }
        $activeMessage = in_array($event->event_type, ['booking.confirmed', 'booking.reminder', 'session.resources_changed'], true);
        if ($activeMessage && ($booking->status !== 'confirmed' || $session->status !== 'published' || $session->starts_at->lte(now()))) {
            return null;
        }
        if (in_array($event->event_type, ['booking.reminder', 'session.resources_changed'], true) && $event->aggregate_version !== $session->version) {
            return null;
        }
        if ($event->event_type === 'booking.reminder' && ($session->starts_at->gt(now()->addDay())
            || $session->starts_at->lte(now()->addDay()->subMinutes(15))
            || $booking->confirmed_at->gte($session->starts_at->subDay()))) {
            return null;
        }
        $subject = match ($event->event_type) {
            'booking.confirmed' => 'Potvrda rezervacije',
            'booking.cancelled' => 'Rezervacija je otkazana',
            'booking.moved' => 'Rezervacija je premještena',
            'session.cancelled' => 'Studio je otkazao trening',
            'session.resources_changed' => 'Promjena instruktora ili prostora',
            'booking.refund_exception' => 'Kredit je vraćen',
            'booking.reminder' => 'Podsjetnik na trening za 24 sata',
            default => null,
        };
        if ($subject === null) {
            return null;
        }
        $hostname = TenantDomain::query()->where('tenant_id', $tenant->id)->where('is_primary', true)
            ->where('is_active', true)->where('verification_status', 'verified')->whereNotNull('verified_at')->value('normalized_hostname');
        if (! is_string($hostname)) {
            return null;
        }
        $generator = clone app(UrlGenerator::class);
        $scheme = (string) config('tenancy.auth_scheme');
        $port = config('tenancy.auth_port');
        $generator->forceRootUrl($scheme.'://'.$hostname.($port ? ':'.$port : ''));
        $generator->forceScheme($scheme);
        $zone = $booking->rules_snapshot['timezone'];
        $lines = ['Studio: '.$tenant->name, 'Trening: '.$session->trainingType()->firstOrFail()->name,
            'Termin: '.$session->starts_at->setTimezone($zone)->format('d.m.Y. H:i').' ('.$zone.')'];
        if ($activeMessage) {
            $lines[] = 'Instruktor: '.$session->instructor()->firstOrFail()->display_name;
            $lines[] = 'Prostor: '.$session->room()->firstOrFail()->name;
        }
        if ($event->event_type === 'booking.moved') {
            $lines[] = 'Izvorni termin je otkazan. Potvrdu novog termina šaljemo zasebno; aktualne rezervacije pogledajte u portalu.';
        }
        if (($event->payload['credit_returned'] ?? false) && $event->event_type !== 'booking.moved') {
            $lines[] = 'Kredit je vraćen. Valjanost i raspoloživa prava pogledajte u portalu.';
        }

        return new BookingNotification($subject, $tenant->name, $generator->route('portal'), $lines, $event->id, $tenant->id);
    }
}
