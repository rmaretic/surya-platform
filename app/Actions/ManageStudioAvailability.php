<?php

namespace App\Actions;

use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\InstructorProfile;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageStudioAvailability
{
    /** @param array<string, mixed> $attributes */
    public function save(User $actor, AvailabilityRule $rule, array $attributes, string $operation = 'save'): void
    {
        Gate::forUser($actor)->authorize('studio-catalog');
        $id = $rule->exists ? $rule->id : null;
        DB::transaction(function () use ($actor, $id, $attributes, $operation): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
            abort_unless($tenant->status === 'active', 404);
            Gate::forUser($actor->fresh() ?? $actor)->authorize('studio-catalog');
            $rule = $id === null ? new AvailabilityRule : AvailabilityRule::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($id);
            $data = $this->validate($attributes, $tenant, $id, $operation);
            $key = $data['operation_key'];
            $fingerprint = hash('sha256', json_encode([$operation, $id, $actor->id, $data], JSON_THROW_ON_ERROR));
            $previous = TenantAuditLog::query()->where('tenant_id', $tenant->id)->where('action', 'availability.'.$operation)->where('changes->operation_key', $key)->first();
            if ($previous !== null) {
                if (($previous->changes['fingerprint'] ?? null) !== $fingerprint) {
                    throw ValidationException::withMessages(['operation_key' => 'Ključ operacije već je iskorišten za drukčiji zahtjev.']);
                }

                return;
            }
            if ($id !== null && $data['revision'] !== $this->revision($rule)) {
                throw ValidationException::withMessages(['revision' => 'Dostupnost je izmijenjena. Osvježite stranicu prije spremanja.']);
            }
            if ($rule->archived_at !== null) {
                throw ValidationException::withMessages(['availability' => 'Dostupnost je zatvorena.']);
            }
            $before = $id === null ? null : clone $rule->load('exceptions');
            if ($operation === 'save') {
                $this->resources($tenant, $data);
                $policy = BookingRuleVersion::query()->where('tenant_id', $tenant->id)->orderByDesc('version')->first();
                if ($policy === null) {
                    throw ValidationException::withMessages(['rules' => 'Owner prvo treba spremiti pravila rezervacije u katalogu.']);
                }
                unset($data['operation_key'], $data['revision']);
                $rule->forceFill($data);
                $rule->timezone = $tenant->timezone;
                $rule->booking_rule_version_id = $policy->id;
                $rule->save();
            } elseif ($operation === 'exception') {
                $date = $data['local_date'];
                if ($date < $rule->starts_on->format('Y-m-d') || ($rule->ends_on !== null && $date > $rule->ends_on->format('Y-m-d'))) {
                    throw ValidationException::withMessages(['local_date' => 'Iznimka mora biti unutar razdoblja dostupnosti.']);
                }
                $exception = $rule->exceptions()->where('tenant_id', $tenant->id)->where('local_date', $date)->first() ?? new AvailabilityException;
                unset($data['operation_key'], $data['revision']);
                $exception->forceFill([...$data, 'availability_rule_id' => $rule->id]);
                $exception->save();
            } else {
                $rule->archived_at = now();
                $rule->save();
            }
            $rule->load('exceptions');
            if ($before !== null) {
                $this->protectSessions($before, $rule, $tenant);
            }
            $audit = new TenantAuditLog([
                'action' => 'availability.'.$operation, 'subject_type' => 'availability_rules', 'subject_id' => $rule->id,
                'changes' => ['operation_key' => $key, 'fingerprint' => $fingerprint, 'before' => $before?->toArray(), 'after' => $rule->toArray()],
            ]);
            $audit->actor()->associate($actor);
            $audit->save();
        }, 3);
    }

    public function revision(AvailabilityRule $rule): string
    {
        $rule->loadMissing('exceptions');

        return hash('sha256', json_encode([$rule->getAttributes(), $rule->exceptions->sortBy('id')->map(fn ($exception) => $exception->getAttributes())->values()->all()], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function validate(array $attributes, Tenant $tenant, ?int $id, string $operation): array
    {
        $rules = [
            'tenant_id' => ['missing'], 'booking_rule_version_id' => ['missing'], 'timezone' => ['missing'],
            'operation_key' => ['required', 'uuid'],
            'revision' => [$id === null ? 'nullable' : 'required', 'string', 'size:64'],
        ];
        if ($operation === 'save') {
            $rules += [
                'training_type_id' => ['required', 'integer', Rule::exists('training_types', 'id')->where('tenant_id', $tenant->id)],
                'instructor_profile_id' => ['required', 'integer', Rule::exists('instructor_profiles', 'id')->where('tenant_id', $tenant->id)],
                'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('tenant_id', $tenant->id)],
                'weekday' => ['required', 'integer', 'between:1,7'],
                'local_start_time' => ['required', 'date_format:H:i'],
                'local_end_time' => ['required', 'date_format:H:i', 'after:local_start_time'],
                'starts_on' => ['required', 'date_format:Y-m-d'],
                'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
                'duration_minutes' => ['required', 'integer', 'between:1,480'],
                'slot_step_minutes' => ['required', 'integer', 'between:1,480'],
                'buffer_before_minutes' => ['required', 'integer', 'between:0,240'],
                'buffer_after_minutes' => ['required', 'integer', 'between:0,240'],
            ];
        } elseif ($operation === 'exception') {
            $rules += [
                'local_date' => ['required', 'date_format:Y-m-d'],
                'is_unavailable' => ['required', 'boolean'],
                'local_start_time' => ['nullable', 'required_if:is_unavailable,0', 'prohibited_if:is_unavailable,1', 'date_format:H:i'],
                'local_end_time' => ['nullable', 'required_if:is_unavailable,0', 'prohibited_if:is_unavailable,1', 'date_format:H:i', 'after:local_start_time'],
                'reason' => ['required', 'string', 'max:2000'],
            ];
        } else {
            abort_unless($operation === 'close' && $id !== null, 404);
        }
        $data = Validator::make($attributes, $rules)->validate();
        if ($operation === 'save') {
            foreach (['training_type_id', 'instructor_profile_id', 'room_id', 'weekday', 'duration_minutes', 'slot_step_minutes', 'buffer_before_minutes', 'buffer_after_minutes'] as $field) {
                $data[$field] = (int) $data[$field];
            }
            $data['ends_on'] ??= null;
            $minutes = fn (string $time): int => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
            $firstStart = (int) ceil($data['buffer_before_minutes'] / $data['slot_step_minutes']) * $data['slot_step_minutes'];
            if ($minutes($data['local_end_time']) - $minutes($data['local_start_time']) < $firstStart + $data['duration_minutes'] + $data['buffer_after_minutes']) {
                throw ValidationException::withMessages(['local_end_time' => 'Prozor mora sadržavati barem jedan slot s trajanjem, korakom i oba buffera.']);
            }
        } elseif ($operation === 'exception') {
            $data['is_unavailable'] = (bool) $data['is_unavailable'];
            if ($data['is_unavailable']) {
                $data['local_start_time'] = null;
                $data['local_end_time'] = null;
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function resources(Tenant $tenant, array $data): void
    {
        $type = TrainingType::query()->where('tenant_id', $tenant->id)->whereKey($data['training_type_id'])->firstOrFail();
        $instructor = InstructorProfile::query()->where('tenant_id', $tenant->id)->with('user')->whereKey($data['instructor_profile_id'])->firstOrFail();
        $room = Room::query()->where('tenant_id', $tenant->id)->whereKey($data['room_id'])->firstOrFail();
        if ($type->mode !== 'private' || $type->archived_at !== null) {
            throw ValidationException::withMessages(['training_type_id' => 'Odaberite aktivnu privatnu vrstu.']);
        }
        if ($instructor->archived_at !== null || ! $instructor->user?->is_active || ! in_array($instructor->user->role, ['owner', 'manager', 'instructor'], true)) {
            throw ValidationException::withMessages(['instructor_profile_id' => 'Odaberite aktivnog instruktora.']);
        }
        if ($room->archived_at !== null) {
            throw ValidationException::withMessages(['room_id' => 'Odaberite aktivan prostor.']);
        }
    }

    private function protectSessions(AvailabilityRule $before, AvailabilityRule $after, Tenant $tenant): void
    {
        $sessions = ClassSession::query()->where('tenant_id', $tenant->id)->whereIn('status', ['draft', 'published'])
            ->whereRaw('DATE_ADD(ends_at, INTERVAL buffer_after_minutes MINUTE) > ?', [now()->format('Y-m-d H:i:s')])->where('training_type_id', $before->training_type_id)
            ->where('instructor_profile_id', $before->instructor_profile_id)->where('room_id', $before->room_id)->orderBy('id')->lockForUpdate()->get();
        foreach ($sessions as $session) {
            $date = $session->starts_at->setTimezone($before->timezone)->format('Y-m-d');
            try {
                $old = app(CalculatePrivateSlots::class)->window($before, $date);
                $blockedStart = $session->starts_at->subMinutes($session->buffer_before_minutes);
                $blockedEnd = $session->ends_at->addMinutes($session->buffer_after_minutes);
                if ($old === null || $blockedStart->lt($old[0]) || $blockedEnd->gt($old[1])) {
                    continue;
                }
                $new = app(CalculatePrivateSlots::class)->window($after, $date);
                $sameConfiguration = $before->only(['training_type_id', 'instructor_profile_id', 'room_id', 'duration_minutes', 'slot_step_minutes', 'buffer_before_minutes', 'buffer_after_minutes']) === $after->only(['training_type_id', 'instructor_profile_id', 'room_id', 'duration_minutes', 'slot_step_minutes', 'buffer_before_minutes', 'buffer_after_minutes']);
                if ($new !== null && $sameConfiguration && $blockedStart->gte($new[0]) && $blockedEnd->lte($new[1])) {
                    continue;
                }
            } catch (ValidationException) {
                /** An ambiguous replacement must not invalidate an existing appointment. */
            }
            throw ValidationException::withMessages(['availability' => 'Izmjena je u konfliktu s postojećim privatnim terminom. Prvo riješite zamjenu ili otkaz termina.']);
        }
    }
}
