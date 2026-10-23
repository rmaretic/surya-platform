<?php

/** Independent PHP/MySQL participant used only by BookingConcurrencyTest. */
use App\Actions\CreateBooking;
use App\Actions\ManageAttendance;
use App\Actions\ManageBooking;
use App\Actions\ManageStudioCancellation;
use App\Actions\ManageStudioSchedule;
use App\Actions\PrepareBookingNotification;
use App\Jobs\DeliverBookingNotification;
use App\Jobs\Middleware\UseTenantContext;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BookingNotification;
use App\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! $app->environment('testing') || $input['database']['database'] !== 'surya_testing' || (int) $input['database']['port'] !== 3308) {
    throw new RuntimeException('Race workers require the dedicated MySQL test database.');
}
config(['database.default' => 'mysql', 'database.connections.mysql' => $input['database']]);
DB::purge('mysql');
Carbon::setTestNow($input['now']);
CarbonImmutable::setTestNow($input['now']);
$tenant = Tenant::findOrFail($input['tenant_id']);
app(TenantContext::class)->setTenant($tenant);
$actor = User::findOrFail($input['actor_id']);
$socket = stream_socket_client($input['barrier'], timeout: 15);
stream_set_timeout($socket, 15);
fwrite($socket, json_encode(['connection' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id])."\n");
if (trim((string) fgets($socket)) !== 'GO') {
    throw new RuntimeException('Race start barrier timed out.');
}
$announced = false;
DB::connection()->beforeExecuting(function (string $query) use ($socket, &$announced): void {
    if (! $announced && str_contains($query, '`tenants`') && str_contains($query, 'for update')) {
        $announced = true;
        fwrite($socket, "ATTEMPT\n");
    }
});
try {
    if (($input['action'] ?? '') === 'notification') {
        config(['mail.default' => 'array', 'mail.mailers.array.transport' => 'array']);
        Notification::fake();
        $job = new DeliverBookingNotification($tenant->id, $input['event_id']);
        (new UseTenantContext($tenant->id))->handle($job, fn () => $job->handle(app(PrepareBookingNotification::class)));
        $result = ['status' => 'ok', 'sent' => Notification::sent($actor, BookingNotification::class)->count(),
            'context_cleared' => app(TenantContext::class)->tenant() === null];
    } elseif (($input['action'] ?? '') === 'attendance') {
        app(ManageAttendance::class)->handle($actor, Booking::findOrFail($input['booking_id']), $input['data']);
        $result = ['status' => 'ok'];
    } elseif (($input['action'] ?? 'booking') === 'schedule') {
        $session = isset($input['session_id']) ? ClassSession::findOrFail($input['session_id']) : new ClassSession;
        app(ManageStudioSchedule::class)->saveSession($actor, $session, $input['data']);
        $result = ['status' => 'ok'];
    } elseif (($input['action'] ?? '') === 'studio_cancel') {
        app(ManageStudioCancellation::class)->cancel($actor, ClassSession::findOrFail($input['session_id']), $input['data']);
        $result = ['status' => 'ok'];
    } elseif (($input['action'] ?? '') === 'refund_exception') {
        app(ManageStudioCancellation::class)->refundException($actor, Booking::findOrFail($input['booking_id']), $input['data']);
        $result = ['status' => 'ok'];
    } elseif (in_array($input['action'] ?? '', ['cancel', 'move'], true)) {
        $booking = app(ManageBooking::class)->handle($actor, Booking::findOrFail($input['booking_id']), $input['data'], $input['action'] === 'move');
        $result = ['status' => 'ok', 'booking_id' => $booking->id];
    } else {
        $booking = app(CreateBooking::class)->handle($actor, $input['data']);
        $result = ['status' => 'ok', 'booking_id' => $booking->id];
    }
} catch (ValidationException $exception) {
    $result = ['status' => 'rejected', 'errors' => $exception->errors()];
} finally {
    app(TenantContext::class)->clear();
    fclose($socket);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
