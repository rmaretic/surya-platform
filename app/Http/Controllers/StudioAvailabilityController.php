<?php

namespace App\Http\Controllers;

use App\Actions\CalculatePrivateSlots;
use App\Actions\ManageStudioAvailability;
use App\Models\AvailabilityRule;
use App\Models\BookingRuleVersion;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudioAvailabilityController extends Controller
{
    public function index(Request $request, CalculatePrivateSlots $slots, ManageStudioAvailability $manage): Response
    {
        Gate::authorize('studio-catalog');
        $tenant = app(TenantContext::class)->requireTenant();
        $filters = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $date = $filters['date'] ?? CarbonImmutable::now($tenant->timezone)->format('Y-m-d');

        return Inertia::render('studio/Availability', [
            'timezone' => $tenant->timezone, 'date' => $date,
            'preview' => $slots->handle($date),
            'operationKey' => (string) Str::uuid(),
            'availability' => AvailabilityRule::query()->where('tenant_id', $tenant->id)->with(['exceptions', 'trainingType', 'instructor', 'room', 'ruleVersion'])->orderByDesc('id')->get()->map(fn (AvailabilityRule $rule): array => $this->ruleData($rule, $manage)),
            'trainingTypes' => TrainingType::query()->where('tenant_id', $tenant->id)->where('mode', 'private')->whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'instructors' => InstructorProfile::query()->where('tenant_id', $tenant->id)->whereNull('archived_at')->whereHas('user', fn ($query) => $query->where('is_active', true)->whereIn('role', ['owner', 'manager', 'instructor']))->orderBy('display_name')->get(['id', 'display_name']),
            'rooms' => Room::query()->where('tenant_id', $tenant->id)->whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'currentRules' => BookingRuleVersion::query()->where('tenant_id', $tenant->id)->orderByDesc('version')->first(['minimum_notice_minutes', 'booking_horizon_days']),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    /** @return array<string, mixed> */
    private function ruleData(AvailabilityRule $rule, ManageStudioAvailability $manage): array
    {
        return [
            ...$rule->only(['id', 'training_type_id', 'instructor_profile_id', 'room_id', 'weekday', 'duration_minutes', 'slot_step_minutes', 'buffer_before_minutes', 'buffer_after_minutes']),
            'starts_on' => $rule->starts_on->format('Y-m-d'), 'ends_on' => $rule->ends_on?->format('Y-m-d'),
            'local_start_time' => substr($rule->local_start_time, 0, 5), 'local_end_time' => substr($rule->local_end_time, 0, 5),
            'archived' => $rule->archived_at !== null, 'revision' => $manage->revision($rule),
            'training_type' => $rule->trainingType?->name, 'instructor' => $rule->instructor?->display_name, 'room' => $rule->room?->name,
            'notice_minutes' => $rule->ruleVersion?->minimum_notice_minutes, 'horizon_days' => $rule->ruleVersion?->booking_horizon_days,
            'exceptions' => $rule->exceptions->sortBy('local_date')->values()->map(fn ($exception): array => [
                'local_date' => $exception->local_date->format('Y-m-d'), 'is_unavailable' => $exception->is_unavailable,
                'local_start_time' => $exception->local_start_time === null ? '' : substr($exception->local_start_time, 0, 5),
                'local_end_time' => $exception->local_end_time === null ? '' : substr($exception->local_end_time, 0, 5), 'reason' => $exception->reason,
            ]),
        ];
    }

    public function store(Request $request, ManageStudioAvailability $manage): RedirectResponse
    {
        $manage->save($this->actor($request), new AvailabilityRule, $request->all());

        return back()->with('status', 'Privatna dostupnost je spremljena.');
    }

    public function update(Request $request, AvailabilityRule $availability, ManageStudioAvailability $manage): RedirectResponse
    {
        $manage->save($this->actor($request), $availability, $request->all());

        return back()->with('status', 'Privatna dostupnost je spremljena.');
    }

    public function exception(Request $request, AvailabilityRule $availability, ManageStudioAvailability $manage): RedirectResponse
    {
        $manage->save($this->actor($request), $availability, $request->all(), 'exception');

        return back()->with('status', 'Iznimka je spremljena.');
    }

    public function close(Request $request, AvailabilityRule $availability, ManageStudioAvailability $manage): RedirectResponse
    {
        $manage->save($this->actor($request), $availability, $request->all(), 'close');

        return back()->with('status', 'Dostupnost je zatvorena.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
