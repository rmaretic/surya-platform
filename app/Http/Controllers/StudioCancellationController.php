<?php

namespace App\Http\Controllers;

use App\Actions\ManageStudioCancellation;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudioCancellationController extends Controller
{
    public function index(Request $request, ManageStudioCancellation $manage): Response
    {
        Gate::authorize('studio-catalog');
        $tenant = app(TenantContext::class)->requireTenant();
        $data = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d'], 'session' => ['sometimes', 'integer']]);
        $date = CarbonImmutable::parse($data['date'] ?? CarbonImmutable::now($tenant->timezone)->format('Y-m-d'), $tenant->timezone)->startOfDay();
        $selected = isset($data['session']) ? ClassSession::query()->where('tenant_id', $tenant->id)->whereKey($data['session'])->firstOrFail() : null;

        return Inertia::render('studio/Cancellations', [
            'date' => $date->format('Y-m-d'), 'timezone' => $tenant->timezone,
            'sessions' => ClassSession::query()->where('tenant_id', $tenant->id)
                ->whereBetween('starts_at', [$date->utc(), $date->endOfDay()->utc()])
                ->with('trainingType:id,name')->orderBy('starts_at')->orderBy('id')->get()
                ->map(fn (ClassSession $session): array => [
                    'id' => $session->id, 'name' => $session->trainingType?->name, 'status' => $session->status,
                    'time' => $session->starts_at->setTimezone($tenant->timezone)->format('H:i'),
                ]),
            'selected' => $selected === null ? null : $manage->preview($this->actor($request), $selected),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function cancel(Request $request, ClassSession $session, ManageStudioCancellation $manage): RedirectResponse
    {
        $manage->cancel($this->actor($request), $session, $request->all());

        return back()->with('status', 'Termin je otkazan i krediti su vraćeni.');
    }

    public function refund(Request $request, Booking $booking, ManageStudioCancellation $manage): RedirectResponse
    {
        $manage->refundException($this->actor($request), $booking, $request->all());

        return back()->with('status', 'Iznimni povrat kredita je evidentiran.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
