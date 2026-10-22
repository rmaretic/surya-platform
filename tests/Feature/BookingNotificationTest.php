<?php

use App\Actions\PrepareBookingNotification;
use App\Jobs\DeliverBookingNotification;
use App\Jobs\Middleware\UseTenantContext;
use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Notifications\BookingNotification;
use App\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function bookingMailEvent(string $type = 'booking.confirmed'): OutboxEvent
{
    $booking = Booking::factory()->create(['confirmed_at' => now()->subDays(2)]);
    $booking->session->forceFill(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()])->save();

    return OutboxEvent::factory()->create(['booking_id' => $booking->id, 'class_session_id' => $booking->class_session_id,
        'recipient_user_id' => $booking->user_id, 'aggregate_version' => $booking->session->version,
        'event_type' => $type, 'available_at' => now()]);
}

function deliverBookingMail(OutboxEvent $event, ?int $tenantId = null): void
{
    $job = new DeliverBookingNotification($tenantId ?? $event->tenant_id, $event->id);
    (new UseTenantContext($job->tenantId))->handle($job, fn () => $job->handle(app(PrepareBookingNotification::class)));
}

test('delivers each supported event once with studio sender content and trusted portal', function (string $type) {
    $this->prepareStudio();
    $this->freezeTime();
    Notification::fake();
    $event = bookingMailEvent($type);
    $recipient = $event->recipient;

    deliverBookingMail($event);
    deliverBookingMail($event);

    Notification::assertSentToTimes($recipient, BookingNotification::class, 1);
    Notification::assertSentTo($recipient, BookingNotification::class, function ($notification) use ($recipient) {
        $mail = $notification->toMail($recipient);
        expect($mail->from)->toBe([config('mail.from.address'), $this->studio->name]);
        expect($notification->portalUrl)->toBe('http://lotus.yoga.test/portal');
        expect(implode(' ', $notification->lines))->toContain($this->studio->name, 'Europe/Zagreb');

        return true;
    });
    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'delivered', 'attempts' => 1]);
    expect(app(TenantContext::class)->tenant())->toBeNull();
})->with(['booking.confirmed', 'booking.cancelled', 'booking.moved', 'session.cancelled', 'session.resources_changed', 'booking.refund_exception', 'booking.reminder']);

test('suppresses reminders that became invalid before worker execution', function (string $change) {
    $this->prepareStudio();
    $this->freezeTime();
    Notification::fake();
    $event = bookingMailEvent('booking.reminder');
    $booking = $event->booking;
    match ($change) {
        'cancelled', 'moved' => $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $change === 'moved' ? 'moved' : 'customer_timely'])->save(),
        'version' => $event->session->forceFill(['version' => 2])->save(),
        'late' => $booking->forceFill(['confirmed_at' => now()])->save(),
        'stale' => $event->session->forceFill(['starts_at' => now()->addHours(23)])->save(),
        'future' => $event->session->forceFill(['starts_at' => now()->addHours(25), 'ends_at' => now()->addHours(26)])->save(),
        'inactive' => $event->recipient->forceFill(['is_active' => false])->save(),
        'domain' => TenantDomain::query()->update(['verification_status' => 'pending', 'verified_at' => null]),
    };

    deliverBookingMail($event);

    Notification::assertNothingSent();
    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'failed', 'last_error_code' => 'suppressed_ineligible']);
})->with(['cancelled', 'moved', 'version', 'late', 'stale', 'future', 'inactive', 'domain']);

test('dispatcher recovers committed pending events and creates only one due reminder', function () {
    $this->prepareStudio();
    $this->freezeTime();
    Queue::fake();
    $event = bookingMailEvent();

    $this->artisan('bookings:dispatch-notifications')->assertSuccessful();
    $this->artisan('bookings:dispatch-notifications')->assertSuccessful();

    $this->assertDatabaseCount('outbox_events', 2);
    $this->assertDatabaseHas('outbox_events', ['booking_id' => $event->booking_id, 'event_type' => 'booking.reminder']);
    Queue::assertPushed(DeliverBookingNotification::class, 4);
    expect(app(TenantContext::class)->tenant())->toBeNull();
});

test('rollback leaves no event for dispatcher to deliver', function () {
    $this->prepareStudio();
    Queue::fake();
    DB::beginTransaction();
    bookingMailEvent();
    DB::rollBack();

    $this->artisan('bookings:dispatch-notifications')->assertSuccessful();

    Queue::assertNothingPushed();
    $this->assertDatabaseCount('outbox_events', 0);
});

test('interrupted attempts require inbox verification before a bounded retry', function () {
    $this->prepareStudio();
    $this->freezeTime();
    Queue::fake();
    $event = bookingMailEvent();
    $event->forceFill(['status' => 'processing', 'locked_at' => now()->subMinutes(6), 'attempts' => 1])->save();
    $event->booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'customer_timely'])->save();

    $this->artisan('bookings:dispatch-notifications')->assertSuccessful();
    Queue::assertNothingPushed();
    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'uncertain']);
    $this->artisan('bookings:dispatch-notifications', ['--tenant' => $this->studio->id, '--resolve' => $event->id, '--outcome' => 'retry'])->assertSuccessful();
    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'pending', 'attempts' => 1]);
});

test('transport ambiguity is retained without automatic retries or exception secrets', function () {
    $this->prepareStudio();
    $event = bookingMailEvent();
    Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('secret SMTP credential'));

    deliverBookingMail($event);
    deliverBookingMail($event);

    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'uncertain', 'attempts' => 1, 'last_error_code' => 'transport_or_ack_uncertain']);
    expect(app(TenantContext::class)->tenant())->toBeNull();
});

test('a job with another tenant cannot deliver the event', function () {
    $this->prepareStudio();
    Notification::fake();
    $event = bookingMailEvent();
    $other = Tenant::factory()->active()->create();

    deliverBookingMail($event, $other->id);

    Notification::assertNothingSent();
    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'pending', 'attempts' => 0]);
});

test('local inbox receives isolated studio content for consecutive tenants', function () {
    $this->prepareStudio();
    $this->freezeTime();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 11025,
        'mail.mailers.smtp.url' => null, 'mail.mailers.smtp.scheme' => 'smtp', 'mail.mailers.smtp.username' => null, 'mail.mailers.smtp.password' => null]);
    foreach (['lotus.yoga.test', 'balance.yoga.test'] as $hostname) {
        if ($hostname === 'balance.yoga.test') {
            $tenant = Tenant::factory()->active()->create(['name' => 'Balance inbox']);
            app(TenantContext::class)->setTenant($tenant);
            TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => $hostname, 'is_primary' => true]);
        }
        $event = bookingMailEvent();
        $email = 'm2-12.'.Str::uuid().'@example.test';
        $event->recipient->forceFill(['email' => $email])->save();
        $name = app(TenantContext::class)->requireTenant()->name;

        deliverBookingMail($event);

        $messages = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/search', ['query' => 'to:'.$email])->throw()->json('messages');
        expect($messages)->toHaveCount(1);
        $body = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/message/'.$messages[0]['ID'])->throw()->json('Text');
        expect($body)->toContain($hostname.'/portal', $name, 'Europe/Zagreb');
        expect($messages[0]['From']['Name'])->toBe($name);
    }
})->group('local-mail');

test('reminder generation respects the exact due and late booking boundaries', function (int $startSeconds, int $confirmedSeconds, bool $expected) {
    $this->prepareStudio();
    $this->freezeTime();
    Queue::fake();
    $event = bookingMailEvent();
    $event->session->forceFill(['starts_at' => now()->addSeconds($startSeconds), 'ends_at' => now()->addSeconds($startSeconds + 3600)])->save();
    $event->booking->forceFill(['confirmed_at' => now()->addSeconds($confirmedSeconds)])->save();

    $this->artisan('bookings:dispatch-notifications')->assertSuccessful();

    $this->assertDatabaseCount('outbox_events', $expected ? 2 : 1);
})->with([
    'exactly 24 hours' => [86400, -1, true],
    'not yet due' => [86401, -3600, false],
    'catch up before grace expires' => [85501, -3600, true],
    'stale after 15 minutes' => [85500, -3600, false],
    'booked at reminder time' => [86400, 0, false],
    'booked after reminder time' => [86340, -30, false],
]);

test('an already sent reminder is not regenerated after a resource version change', function () {
    $this->prepareStudio();
    $this->freezeTime();
    Queue::fake();
    $event = bookingMailEvent('booking.reminder');
    $event->forceFill(['status' => 'delivered', 'processed_at' => now()])->save();
    $event->session->forceFill(['version' => 2])->save();

    $this->artisan('bookings:dispatch-notifications')->assertSuccessful();

    $this->assertDatabaseCount('outbox_events', 1);
    Queue::assertNothingPushed();
});

test('operator retry is limited but final delivery acknowledgement is still possible', function () {
    $this->prepareStudio();
    $event = bookingMailEvent();
    $event->forceFill(['status' => 'uncertain', 'attempts' => 3])->save();

    $this->artisan('bookings:dispatch-notifications', ['--tenant' => $this->studio->id, '--resolve' => $event->id, '--outcome' => 'retry'])->assertSuccessful();
    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'uncertain']);
    $this->artisan('bookings:dispatch-notifications', ['--tenant' => $this->studio->id, '--resolve' => $event->id, '--outcome' => 'delivered'])->assertSuccessful();
    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'delivered', 'attempts' => 3]);
});

test('external mail transport cannot send M2 notifications', function () {
    $this->prepareStudio();
    Notification::fake();
    $event = bookingMailEvent();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com']);

    deliverBookingMail($event);

    Notification::assertNothingSent();
    $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'pending', 'attempts' => 0]);
});

test('notification rendering escapes studio and training text', function () {
    $notification = new BookingNotification('Potvrda rezervacije', 'Studio', 'http://lotus.yoga.test/portal', ['<script>alert("private")</script>'], 1, 1);

    $html = (string) $notification->toMail(new stdClass)->render();

    expect($html)->toContain('&lt;script&gt;')->not->toContain('<script>alert');
});
