<?php

namespace App\Actions;

use App\Models\AvailabilityRule;
use App\Models\ClassSession;
use App\Models\CreditGrant;
use App\Models\InstructorProfile;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;

class ReadCustomerSchedule
{
    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters, ?User $customer = null): array
    {
        $tenant = app(TenantContext::class)->requireTenant();
        $day = CarbonImmutable::parse($filters['date'], $tenant->timezone);
        $sessions = ClassSession::query()->where('tenant_id', $tenant->id)->where('status', 'published')
            ->where('starts_at', '>=', $day->utc())->where('starts_at', '<', $day->addDay()->utc())
            ->whereHas('trainingType', fn ($q) => $q->where('mode', 'group')->whereNull('archived_at'))
            ->whereHas('instructor', fn ($q) => $q->whereNull('archived_at')->whereHas('user', fn ($u) => $u->where('is_active', true)->whereIn('role', ['owner', 'manager', 'instructor'])))
            ->whereHas('room', fn ($q) => $q->whereNull('archived_at'))
            ->when($filters['type'] ?? null, fn ($q, $id) => $q->where('training_type_id', $id))
            ->when($filters['instructor'] ?? null, fn ($q, $id) => $q->where('instructor_profile_id', $id))
            ->with(['trainingType', 'instructor', 'room', 'ruleVersion'])
            ->withCount(['bookings as confirmed_count' => fn ($q) => $q->where('status', 'confirmed')])
            ->orderBy('starts_at')->orderBy('id')->get();
        $items = [];
        foreach ($sessions as $session) {
            $policy = $session->ruleVersion;
            if ($policy === null) {
                continue;
            }
            $free = max(0, $session->capacity - (int) $session->getAttribute('confirmed_count'));
            $now = CarbonImmutable::now('UTC');
            $withinWindow = $session->starts_at->gt($now) && $session->starts_at->gte($now->addMinutes($policy->minimum_notice_minutes))
                && $session->starts_at->lte($now->setTimezone($tenant->timezone)->addDays($policy->booking_horizon_days)->utc());
            $items[] = [
                ...$this->session($session), 'key' => 'session-'.$session->id,
                'target' => ['class_session_id' => $session->id], 'capacity' => $session->capacity, 'remaining' => $free,
                'available' => $free > 0 && $withinWindow,
                'unavailable_reason' => $free === 0 ? 'Termin je popunjen.' : ($withinWindow ? null : 'Izvan roka za rezervaciju.'),
                'cancellation_minutes' => $policy->group_cancellation_minutes,
            ];
        }
        $private = app(CalculatePrivateSlots::class)->handle($filters['date']);
        $rules = AvailabilityRule::query()->where('tenant_id', $tenant->id)->with('ruleVersion')
            ->whereIn('id', array_column($private['slots'], 'availability_rule_id'))->get()->keyBy('id');
        foreach ($private['slots'] as $slot) {
            $rule = $rules->get($slot['availability_rule_id']);
            if ($rule === null || (($filters['type'] ?? null) && $rule->training_type_id !== (int) $filters['type'])
                || (($filters['instructor'] ?? null) && $rule->instructor_profile_id !== (int) $filters['instructor'])) {
                continue;
            }
            $items[] = [
                'key' => 'slot-'.$rule->id.'-'.$slot['starts_at'],
                'target' => ['availability_rule_id' => $rule->id, 'starts_at' => $slot['starts_at']],
                'training_type_id' => $rule->training_type_id, 'name' => $slot['training_type'], 'mode' => 'private',
                'instructor' => $slot['instructor'], 'room' => $slot['room'],
                'starts_at' => $slot['starts_at'], 'ends_at' => $slot['ends_at'], 'duration_minutes' => $slot['duration_minutes'],
                'capacity' => 1, 'remaining' => 1, 'available' => true, 'unavailable_reason' => null,
                'cancellation_minutes' => $rule->ruleVersion?->private_cancellation_minutes,
            ];
        }
        usort($items, fn (array $a, array $b): int => [$a['starts_at'], $a['key']] <=> [$b['starts_at'], $b['key']]);
        if ($customer !== null) {
            $grants = CreditGrant::query()->where('tenant_id', $tenant->id)->where('user_id', $customer->id)
                ->with('trainingTypes')->withSum('entries as balance', 'amount')->get();
            foreach ($items as &$item) {
                $start = CarbonImmutable::parse($item['starts_at']);
                $item['has_credit'] = $grants->contains(fn (CreditGrant $grant): bool => (int) $grant->getAttribute('balance') > 0
                    && $start->gte($grant->valid_from) && $start->lt($grant->expires_at)
                    && $grant->trainingTypes->contains('id', $item['training_type_id']));
            }
            unset($item);
        }

        return [
            'filters' => $filters, 'timezone' => $tenant->timezone, 'items' => $items,
            'notice' => $private['warnings'] === [] ? null : 'Neke privatne termine nije moguće prikazati. Kontaktirajte studio.',
            'types' => TrainingType::query()->where('tenant_id', $tenant->id)->whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->toArray(),
            'instructors' => InstructorProfile::query()->where('tenant_id', $tenant->id)->whereNull('archived_at')
                ->whereHas('user', fn ($q) => $q->where('is_active', true)->whereIn('role', ['owner', 'manager', 'instructor']))
                ->orderBy('display_name')->get(['id', 'display_name'])->map(fn ($i) => ['id' => $i->id, 'name' => $i->display_name])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function session(ClassSession $session): array
    {
        return [
            'training_type_id' => $session->training_type_id, 'name' => $session->trainingType?->name,
            'mode' => $session->trainingType?->mode, 'instructor' => $session->instructor?->display_name,
            'room' => $session->room?->name, 'starts_at' => $session->starts_at->toIso8601String(),
            'ends_at' => $session->ends_at->toIso8601String(),
            'duration_minutes' => (int) $session->starts_at->diffInMinutes($session->ends_at),
        ];
    }
}
