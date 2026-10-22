<?php

namespace App\Http\Controllers;

use App\Actions\CreditLedger;
use App\Actions\ManageBooking;
use App\Actions\ReadCustomerSchedule;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\CreditGrant;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    public function schedule(Request $request, ReadCustomerSchedule $schedule, PublicStudioController $public): Response
    {
        $tenant = app(TenantContext::class)->requireTenant();
        $data = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'type' => ['nullable', 'integer', 'min:1'], 'instructor' => ['nullable', 'integer', 'min:1'],
            'intent' => ['nullable', 'string', 'max:150'], 'move' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters = ['date' => $data['date'] ?? now($tenant->timezone)->format('Y-m-d'), 'type' => $data['type'] ?? null, 'instructor' => $data['instructor'] ?? null];
        $authenticated = $request->routeIs('reserve');
        $moving = null;
        if ($authenticated && isset($data['move'])) {
            $booking = Booking::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()?->id)->whereKey($data['move'])->firstOrFail();
            $moving = ['id' => $booking->id, 'preview' => app(ManageBooking::class)->preview($this->customer($request), $booking)];
        }

        return $public->renderPage('schedule', [
            ...$schedule->handle($filters, $authenticated && $moving === null ? $this->customer($request) : null), 'authenticated' => $authenticated,
            'intent' => $authenticated ? ($data['intent'] ?? null) : null,
            'moving' => $moving, 'operationKey' => (string) Str::uuid(),
        ]);
    }

    public function index(Request $request, PublicStudioController $public, ReadCustomerSchedule $schedule, CreditLedger $ledger): Response
    {
        $customer = $this->customer($request);
        $tenant = app(TenantContext::class)->requireTenant();
        $data = $request->validate(['booking' => ['nullable', 'integer', 'min:1'], 'period' => ['nullable', 'in:upcoming,history']]);
        $period = $data['period'] ?? 'upcoming';
        $query = Booking::query()->where('tenant_id', $tenant->id)->where('user_id', $customer->id);
        $selected = isset($data['booking']) ? (clone $query)->with(['session.trainingType', 'session.instructor', 'session.room'])->whereKey($data['booking'])->firstOrFail() : null;
        $bookings = (clone $query)->with(['session.trainingType', 'session.instructor', 'session.room'])
            ->whereHas('session', fn ($q) => $q->where('starts_at', $period === 'history' ? '<' : '>=', now()))
            ->orderByDesc('id')->paginate(12, ['*'], 'bookings_page')->withQueryString()
            ->through(fn (Booking $booking) => ['id' => $booking->id, 'status' => $booking->status, 'session' => $schedule->session($booking->session)]);
        $grants = CreditGrant::query()->where('tenant_id', $tenant->id)->where('user_id', $customer->id)
            ->with('trainingTypes')->orderByDesc('id')->paginate(10, ['*'], 'grants_page')->withQueryString();
        $balances = $ledger->balances($grants->getCollection());
        $grants->through(fn (CreditGrant $grant) => [
            'id' => $grant->id, 'name' => $grant->product_snapshot['name'], 'source' => $grant->source,
            'valid_from' => CarbonImmutable::parse($grant->valid_from)->toIso8601String(), 'expires_at' => CarbonImmutable::parse($grant->expires_at)->toIso8601String(),
            'balance' => $balances[$grant->id], 'types' => $grant->trainingTypes->pluck('name')->all(),
        ]);
        $entries = CreditEntry::query()->where('tenant_id', $tenant->id)->whereHas('grant', fn ($q) => $q->where('user_id', $customer->id))
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(15, ['*'], 'entries_page')->withQueryString()
            ->through(fn (CreditEntry $entry) => $entry->only(['id', 'credit_grant_id', 'booking_id', 'kind', 'amount', 'occurred_at']));

        return $public->renderPage('portal', [
            'timezone' => $tenant->timezone, 'period' => $period, 'bookings' => $bookings, 'grants' => $grants, 'entries' => $entries,
            'selected' => $selected === null ? null : [
                'id' => $selected->id, 'status' => $selected->status, 'session' => $schedule->session($selected->session),
                'credits_used' => $selected->credits_used, 'preview' => app(ManageBooking::class)->preview($customer, $selected),
            ], 'operationKey' => (string) Str::uuid(),
        ]);
    }

    private function customer(Request $request): User
    {
        Gate::authorize('customer-portal');
        $customer = $request->user();
        abort_unless($customer instanceof User, 403);

        return $customer;
    }
}
