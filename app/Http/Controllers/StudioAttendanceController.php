<?php

namespace App\Http\Controllers;

use App\Actions\ManageAttendance;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudioAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('studio-administration');
        $actor = $this->actor($request);
        $tenant = app(TenantContext::class)->requireTenant();
        $data = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d'], 'session' => ['sometimes', 'integer']]);
        $date = CarbonImmutable::parse($data['date'] ?? CarbonImmutable::now($tenant->timezone)->format('Y-m-d'), $tenant->timezone)->startOfDay();
        $sessions = ClassSession::query()->where('tenant_id', $tenant->id)
            ->when($actor->role === 'instructor', fn ($query) => $query->whereHas('instructor', fn ($query) => $query
                ->where('tenant_id', $tenant->id)->where('user_id', $actor->id)->whereNull('archived_at')));
        $selected = isset($data['session']) ? (clone $sessions)->whereKey($data['session'])->firstOrFail() : null;
        if ($selected !== null) {
            Gate::authorize('studio-attendance', $selected);
            $selected->load(['trainingType:id,name', 'bookings.user:id,name', 'bookings.attendance.recorder:id,name']);
        }

        return Inertia::render('studio/Attendance', [
            'date' => $date->format('Y-m-d'), 'timezone' => $tenant->timezone,
            'operationKey' => (string) Str::uuid(),
            'sessions' => $sessions->whereBetween('starts_at', [$date->utc(), $date->endOfDay()->utc()])
                ->with('trainingType:id,name')->orderBy('starts_at')->orderBy('id')->get()
                ->map(fn (ClassSession $session): array => [
                    'id' => $session->id, 'name' => $session->trainingType?->name,
                    'status' => $session->status, 'time' => $session->starts_at->setTimezone($tenant->timezone)->format('H:i'),
                ]),
            'selected' => $selected === null ? null : [
                'id' => $selected->id, 'name' => $selected->trainingType?->name,
                'local_start' => $selected->starts_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                'can_record' => in_array($selected->status, ['published', 'completed'], true),
                'can_mark_no_show' => $selected->ends_at->lte(now()),
                'bookings' => $selected->bookings->sortBy('id')->values()->map(fn (Booking $booking): array => [
                    'id' => $booking->id, 'name' => $booking->user?->name,
                    'booking_status' => $booking->status, 'status' => $booking->attendance->status ?? 'pending',
                    'recorded_at' => $booking->attendance?->recorded_at?->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                    'recorded_by' => $booking->attendance?->recorder?->name,
                ]),
            ],
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, Booking $booking, ManageAttendance $manage): RedirectResponse
    {
        $manage->handle($this->actor($request), $booking, $request->all());

        return back()->with('status', 'Evidencija dolaska je spremljena.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
