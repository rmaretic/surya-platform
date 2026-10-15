<?php

namespace App\Actions;

use App\Models\AvailabilityRule;
use App\Models\ClassSession;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CalculatePrivateSlots
{
    /** @return array{slots: list<array<string, mixed>>, warnings: list<string>} */
    public function handle(string $date, ?int $ruleId = null): array
    {
        $tenant = app(TenantContext::class)->requireTenant();
        $rules = AvailabilityRule::query()->where('tenant_id', $tenant->id)->whereNull('archived_at')
            ->when($ruleId !== null, fn ($query) => $query->whereKey($ruleId))
            ->whereHas('trainingType', fn ($query) => $query->where('mode', 'private')->whereNull('archived_at'))
            ->whereHas('room', fn ($query) => $query->whereNull('archived_at'))
            ->whereHas('instructor', fn ($query) => $query->whereNull('archived_at')->whereHas('user', fn ($user) => $user->where('is_active', true)->whereIn('role', ['owner', 'manager', 'instructor'])))
            ->with(['exceptions', 'ruleVersion', 'trainingType', 'instructor', 'room'])->orderBy('id')->get();
        $day = CarbonImmutable::parse($date, $tenant->timezone);
        $sessions = ClassSession::query()->where('tenant_id', $tenant->id)->whereIn('status', ['draft', 'published'])
            ->whereRaw('DATE_SUB(starts_at, INTERVAL buffer_before_minutes MINUTE) < ?', [$day->addDay()->utc()->format('Y-m-d H:i:s')])
            ->whereRaw('DATE_ADD(ends_at, INTERVAL buffer_after_minutes MINUTE) > ?', [$day->utc()->format('Y-m-d H:i:s')])->get();
        $slots = [];
        $warnings = [];
        $now = CarbonImmutable::now('UTC');
        foreach ($rules as $rule) {
            $policy = $rule->ruleVersion;
            if ($policy === null) {
                continue;
            }
            try {
                $window = $this->window($rule, $date);
                if ($window === null) {
                    continue;
                }
                [$from, $to] = $window;
                $wall = CarbonImmutable::parse($from->setTimezone($rule->timezone)->format('Y-m-d H:i'), 'UTC');
                $wallEnd = CarbonImmutable::parse($to->setTimezone($rule->timezone)->format('Y-m-d H:i'), 'UTC');
                $ruleSlots = [];
                for (; $wall->lt($wallEnd); $wall = $wall->addMinutes($rule->slot_step_minutes)) {
                    $start = app(ResolveStudioLocalTime::class)->handle($wall->format('Y-m-d H:i'), $rule->timezone, field: 'date');
                    $end = $start->addMinutes($rule->duration_minutes);
                    $blockedStart = $start->subMinutes($rule->buffer_before_minutes);
                    $blockedEnd = $end->addMinutes($rule->buffer_after_minutes);
                    if ($blockedStart->lt($from) || $blockedEnd->gt($to)
                        || $start->lt($now->addMinutes($policy->minimum_notice_minutes))
                        || $start->gt($now->setTimezone($rule->timezone)->addDays($policy->booking_horizon_days)->utc())) {
                        continue;
                    }
                    if ($sessions->contains(fn (ClassSession $session): bool => ($session->instructor_profile_id === $rule->instructor_profile_id || $session->room_id === $rule->room_id)
                        && $session->starts_at->subMinutes($session->buffer_before_minutes)->lt($blockedEnd) && $session->ends_at->addMinutes($session->buffer_after_minutes)->gt($blockedStart))) {
                        continue;
                    }
                    $key = implode(':', [$rule->training_type_id, $rule->instructor_profile_id, $rule->room_id, $start->timestamp, $end->timestamp]);
                    $ruleSlots[$key] = [
                        'availability_rule_id' => $rule->id,
                        'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(),
                        'local_start' => $start->setTimezone($rule->timezone)->format('H:i'),
                        'local_end' => $end->setTimezone($rule->timezone)->format('H:i'),
                        'utc_offset' => $start->setTimezone($rule->timezone)->format('P'),
                        'training_type' => $rule->trainingType?->name, 'instructor' => $rule->instructor?->display_name, 'room' => $rule->room?->name,
                        'duration_minutes' => $rule->duration_minutes,
                    ];
                }
                $slots += $ruleSlots;
            } catch (ValidationException $exception) {
                $warnings[] = 'Dostupnost #'.$rule->id.': '.collect($exception->errors())->flatten()->first().' Dodajte iznimku s nedvosmislenim vremenom.';
            }
        }
        usort($slots, fn (array $a, array $b): int => [$a['starts_at'], $a['availability_rule_id']] <=> [$b['starts_at'], $b['availability_rule_id']]);

        return ['slots' => $slots, 'warnings' => array_values(array_unique($warnings))];
    }

    /** @return array{CarbonImmutable, CarbonImmutable}|null */
    public function window(AvailabilityRule $rule, string $date): ?array
    {
        if ($rule->archived_at !== null || $date < $rule->starts_on->format('Y-m-d') || ($rule->ends_on !== null && $date > $rule->ends_on->format('Y-m-d'))) {
            return null;
        }
        $exception = $rule->exceptions->first(fn ($item): bool => $item->local_date->format('Y-m-d') === $date);
        if ($exception?->is_unavailable || ($exception === null && CarbonImmutable::parse($date)->isoWeekday() !== $rule->weekday)) {
            return null;
        }
        $start = $exception->local_start_time ?? $rule->local_start_time;
        $end = $exception->local_end_time ?? $rule->local_end_time;

        return [
            app(ResolveStudioLocalTime::class)->handle($date.' '.substr($start, 0, 5), $rule->timezone, field: 'date'),
            app(ResolveStudioLocalTime::class)->handle($date.' '.substr($end, 0, 5), $rule->timezone, field: 'date'),
        ];
    }
}
