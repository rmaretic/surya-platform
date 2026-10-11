<?php

namespace App\Http\Controllers;

use App\Actions\ManageStudioSchedule;
use App\Models\ClassSession;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\ScheduleSeries;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudioScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('studio-catalog');
        $tenant = app(TenantContext::class)->requireTenant();
        $filters = $request->validate(['from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $from = CarbonImmutable::parse($filters['from'] ?? CarbonImmutable::now($tenant->timezone)->format('Y-m-d'), $tenant->timezone)->startOfDay();
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to'], $tenant->timezone)->endOfDay() : $from->addWeeks(8)->endOfDay();
        abort_if($from->diffInDays($to) > 366, 422);

        return Inertia::render('studio/Schedule', [
            'timezone' => $tenant->timezone,
            'filters' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'sessions' => ClassSession::query()->where('tenant_id', $tenant->id)->whereBetween('starts_at', [$from->utc(), $to->utc()])
                ->whereHas('trainingType', fn ($query) => $query->where('mode', 'group'))
                ->with(['trainingType:id,name', 'instructor:id,display_name', 'room:id,name'])
                ->withCount(['bookings as confirmed_count' => fn ($query) => $query->where('status', 'confirmed')])
                ->orderBy('starts_at')->orderBy('id')->paginate(30)->withQueryString()
                ->through(fn (ClassSession $session): array => [
                    ...$session->only(['id', 'training_type_id', 'instructor_profile_id', 'room_id', 'schedule_series_id', 'capacity', 'status', 'version', 'confirmed_count', 'cancellation_reason']),
                    'local_start' => $session->starts_at->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
                    'utc_offset' => $session->starts_at->setTimezone($tenant->timezone)->format('P'),
                    'duration_minutes' => (int) $session->starts_at->diffInMinutes($session->ends_at),
                    'type_name' => $session->trainingType?->name,
                    'instructor_name' => $session->instructor?->display_name,
                    'room_name' => $session->room?->name,
                    'editable' => $session->starts_at->gt(now()) && in_array($session->status, ['draft', 'published'], true),
                ]),
            'series' => ScheduleSeries::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->get(['id', 'starts_on', 'ends_on', 'weekdays', 'local_start_time', 'timezone', 'archived_at']),
            'trainingTypes' => TrainingType::query()->where('tenant_id', $tenant->id)->where('mode', 'group')->whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'instructors' => InstructorProfile::query()->where('tenant_id', $tenant->id)->whereNull('archived_at')->whereHas('user', fn ($query) => $query->where('is_active', true))->orderBy('display_name')->get(['id', 'display_name']),
            'rooms' => Room::query()->where('tenant_id', $tenant->id)->whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, ManageStudioSchedule $manage): RedirectResponse
    {
        $manage->saveSession($this->actor($request), new ClassSession, $request->all());

        return $this->saved();
    }

    public function update(Request $request, ClassSession $session, ManageStudioSchedule $manage): RedirectResponse
    {
        $manage->saveSession($this->actor($request), $session, $request->all());

        return $this->saved();
    }

    public function storeSeries(Request $request, ManageStudioSchedule $manage): RedirectResponse
    {
        $manage->createSeries($this->actor($request), $request->all());

        return $this->saved();
    }

    public function generate(Request $request, ScheduleSeries $series, ManageStudioSchedule $manage): RedirectResponse
    {
        $manage->generate($this->actor($request), $series, $request->all());

        return $this->saved();
    }

    public function closeSeries(Request $request, ScheduleSeries $series, ManageStudioSchedule $manage): RedirectResponse
    {
        $manage->closeSeries($this->actor($request), $series);

        return $this->saved();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function saved(): RedirectResponse
    {
        return back()->with('status', 'Raspored je spremljen.');
    }
}
