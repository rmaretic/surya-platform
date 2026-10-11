<?php

namespace App\Actions;

use App\Models\BookingRuleVersion;
use App\Models\ClassSession;
use App\Models\InstructorProfile;
use App\Models\OutboxEvent;
use App\Models\Room;
use App\Models\ScheduleSeries;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageStudioSchedule
{
    /** @param array<string, mixed> $attributes */
    public function saveSession(User $actor, ClassSession $session, array $attributes): void
    {
        Gate::forUser($actor)->authorize('studio-catalog');
        $sessionId = $session->exists ? $session->id : null;
        DB::transaction(function () use ($actor, $sessionId, $attributes): void {
            $tenant = $this->lockTenant($actor);
            $session = $sessionId === null ? new ClassSession : ClassSession::query()->where('tenant_id', $tenant->id)
                ->whereHas('trainingType', fn ($query) => $query->where('mode', 'group'))->lockForUpdate()->findOrFail($sessionId);
            $data = $this->validate($attributes, $tenant, false);
            if (($data['scope'] ?? 'single') === 'future') {
                $this->updateFuture($actor, $tenant, $session, $data);
            } else {
                $this->writeSession($actor, $tenant, $session, $data);
            }
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function createSeries(User $actor, array $attributes): void
    {
        Gate::forUser($actor)->authorize('studio-catalog');
        DB::transaction(function () use ($actor, $attributes): void {
            $tenant = $this->lockTenant($actor);
            $data = $this->validate($attributes, $tenant, true);
            $this->resources($tenant, $data);
            $series = (new ScheduleSeries)->forceFill($data);
            $series->timezone = $tenant->timezone;
            $series->booking_rule_version_id = $this->ruleVersion($tenant);
            $series->save();
            $this->audit($actor, 'schedule.series_created', $series->id);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function generate(User $actor, ScheduleSeries $series, array $attributes): void
    {
        Gate::forUser($actor)->authorize('studio-catalog');
        DB::transaction(function () use ($actor, $series, $attributes): void {
            $tenant = $this->lockTenant($actor);
            $series = ScheduleSeries::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($series->id);
            $data = Validator::make($attributes, [
                'tenant_id' => ['missing'],
                'corrections' => ['sometimes', 'array', 'max:56'],
                'corrections.*' => ['array:date,time,offset'],
                'corrections.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
                'corrections.*.time' => ['required', 'date_format:H:i'],
                'corrections.*.offset' => ['nullable', 'regex:/^[+-][0-9]{2}:[0-9]{2}$/'],
            ])->validate();
            if ($series->archived_at !== null) {
                throw ValidationException::withMessages(['series' => 'Serija je zatvorena.']);
            }
            $corrections = [];
            foreach ($data['corrections'] ?? [] as $correction) {
                $corrections[$correction['date']] = $correction;
            }
            $today = CarbonImmutable::now($series->timezone)->startOfDay();
            $end = $today->addWeeks(8);
            $created = 0;
            for ($date = $today->max($series->starts_on->format('Y-m-d')); $date->lt($end); $date = $date->addDay()) {
                if ($series->ends_on !== null && $date->format('Y-m-d') > $series->ends_on->format('Y-m-d')) {
                    break;
                }
                if (! in_array($date->isoWeekday(), $series->weekdays)) {
                    continue;
                }
                $key = $date->format('Y-m-d');
                if ($series->sessions()->where('tenant_id', $tenant->id)->where('occurrence_key', $key)->exists()) {
                    continue;
                }
                $correction = $corrections[$key] ?? null;
                $sessionData = [
                    ...$series->only(['training_type_id', 'instructor_profile_id', 'room_id', 'duration_minutes', 'capacity']),
                    'local_start' => $key.' '.($correction['time'] ?? substr($series->local_start_time, 0, 5)),
                    'utc_offset' => $correction['offset'] ?? null,
                    'status' => 'draft',
                ];
                $start = app(ResolveStudioLocalTime::class)->handle($sessionData['local_start'], $series->timezone, $sessionData['utc_offset'], 'corrections');
                if ($start->lte(now())) {
                    continue;
                }
                $session = (new ClassSession)->forceFill(['schedule_series_id' => $series->id, 'occurrence_key' => $key, 'booking_rule_version_id' => $series->booking_rule_version_id]);
                $this->writeSession($actor, $tenant, $session, $sessionData, $series->timezone);
                $created++;
            }
            if ($created > 0) {
                $this->audit($actor, 'schedule.generated', $series->id, ['created' => $created]);
            }
        }, 3);
    }

    public function closeSeries(User $actor, ScheduleSeries $series): void
    {
        Gate::forUser($actor)->authorize('studio-catalog');
        DB::transaction(function () use ($actor, $series): void {
            $tenant = $this->lockTenant($actor);
            $series = ScheduleSeries::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($series->id);
            if ($series->archived_at === null) {
                $series->archived_at = now();
                $series->save();
                $this->audit($actor, 'schedule.series_closed', $series->id);
            }
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function validate(array $attributes, Tenant $tenant, bool $series): array
    {
        return Validator::make($attributes, [
            'tenant_id' => ['missing'],
            'training_type_id' => ['required', 'integer', Rule::exists('training_types', 'id')->where('tenant_id', $tenant->id)],
            'instructor_profile_id' => ['required', 'integer', Rule::exists('instructor_profiles', 'id')->where('tenant_id', $tenant->id)],
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('tenant_id', $tenant->id)],
            'duration_minutes' => ['required', 'integer', 'between:1,480'],
            'capacity' => ['required', 'integer', 'between:1,500'],
            ...($series ? [
                'weekdays' => ['required', 'array', 'min:1', 'max:7'],
                'weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
                'local_start_time' => ['required', 'date_format:H:i'],
                'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::now($tenant->timezone)->format('Y-m-d')],
                'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            ] : [
                'local_start' => ['required', 'date_format:Y-m-d H:i'],
                'utc_offset' => ['nullable', 'regex:/^[+-][0-9]{2}:[0-9]{2}$/'],
                'status' => ['required', Rule::in(['draft', 'published', 'cancelled'])],
                'cancellation_reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:2000'],
                'scope' => ['sometimes', Rule::in(['single', 'future'])],
                'version' => ['sometimes', 'integer', 'min:1'],
            ]),
        ])->validate();
    }

    /** @param array<string, mixed> $data */
    private function resources(Tenant $tenant, array $data): void
    {
        $type = TrainingType::query()->where('tenant_id', $tenant->id)->whereKey($data['training_type_id'])->firstOrFail();
        $instructor = InstructorProfile::query()->where('tenant_id', $tenant->id)->with('user')->whereKey($data['instructor_profile_id'])->firstOrFail();
        $room = Room::query()->where('tenant_id', $tenant->id)->whereKey($data['room_id'])->firstOrFail();
        if ($type->mode !== 'group' || $type->archived_at !== null) {
            throw ValidationException::withMessages(['training_type_id' => 'Odaberite aktivnu grupnu vrstu.']);
        }
        if ($instructor->archived_at !== null || ! $instructor->user?->is_active || ! in_array($instructor->user->role, ['owner', 'manager', 'instructor'], true)) {
            throw ValidationException::withMessages(['instructor_profile_id' => 'Odaberite aktivnog instruktora.']);
        }
        if ($room->archived_at !== null || $data['capacity'] > $room->capacity) {
            throw ValidationException::withMessages(['capacity' => 'Potreban je aktivan prostor dovoljnog kapaciteta.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function writeSession(User $actor, Tenant $tenant, ClassSession $session, array $data, ?string $timezone = null): void
    {
        if ($session->exists && (in_array($session->status, ['cancelled', 'completed'], true) || $session->starts_at->lte(now()))) {
            throw ValidationException::withMessages(['status' => 'Prošli, otkazani i dovršeni termini ne uređuju se.']);
        }
        if ($session->exists && isset($data['version']) && (int) $data['version'] !== $session->version) {
            throw ValidationException::withMessages(['version' => 'Termin je izmijenjen. Osvježite raspored prije spremanja.']);
        }
        $start = app(ResolveStudioLocalTime::class)->handle($data['local_start'], $timezone ?? $tenant->timezone, $data['utc_offset'] ?? null);
        $end = $start->addMinutes((int) $data['duration_minutes']);
        if ($start->lte(now())) {
            throw ValidationException::withMessages(['local_start' => 'Početak mora biti u budućnosti.']);
        }
        $bookings = $session->exists ? $session->bookings()->where('tenant_id', $tenant->id)->where('status', 'confirmed')->orderBy('id')->lockForUpdate()->get() : collect();
        if ($bookings->isNotEmpty() && (! $start->equalTo($session->starts_at) || ! $end->equalTo($session->ends_at) || (int) $data['training_type_id'] !== $session->training_type_id || $data['status'] !== $session->status)) {
            throw ValidationException::withMessages(['local_start' => 'Rezervirani termin zahtijeva otkaz/zamjenu za promjenu vremena, trajanja, vrste ili statusa.']);
        }
        if ($data['capacity'] < $bookings->count()) {
            throw ValidationException::withMessages(['capacity' => 'Kapacitet ne smije biti manji od broja potvrđenih rezervacija.']);
        }
        if ($data['status'] !== 'cancelled') {
            $this->resources($tenant, $data);
            $conflict = ClassSession::query()->where('tenant_id', $tenant->id)->whereIn('status', ['draft', 'published'])
                ->when($session->exists, fn ($query) => $query->where('id', '!=', $session->id))
                ->where(fn ($query) => $query->where('instructor_profile_id', $data['instructor_profile_id'])->orWhere('room_id', $data['room_id']))
                ->whereRaw('DATE_SUB(starts_at, INTERVAL buffer_before_minutes MINUTE) < ?', [$end->addMinutes($session->buffer_after_minutes ?? 0)->format('Y-m-d H:i:s')])
                ->whereRaw('DATE_ADD(ends_at, INTERVAL buffer_after_minutes MINUTE) > ?', [$start->subMinutes($session->buffer_before_minutes ?? 0)->format('Y-m-d H:i:s')])->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['local_start' => 'Instruktor ili prostor već je zauzet (uključujući buffer).']);
            }
        }
        $resourceChanged = $session->exists && ($session->instructor_profile_id !== (int) $data['instructor_profile_id'] || $session->room_id !== (int) $data['room_id']);
        $before = $session->exists ? $session->only(['starts_at', 'ends_at', 'training_type_id', 'instructor_profile_id', 'room_id', 'capacity', 'status']) : [];
        $session->forceFill(collect($data)->only(['training_type_id', 'instructor_profile_id', 'room_id', 'capacity', 'status'])->all());
        $session->starts_at = $start;
        $session->ends_at = $end;
        $session->booking_rule_version_id ??= $this->ruleVersion($tenant);
        if ($data['status'] === 'cancelled') {
            $session->cancelled_at = now();
            $session->cancellation_reason = $data['cancellation_reason'];
        }
        if ($session->exists && ! $session->isDirty()) {
            return;
        }
        $session->version = $session->exists ? $session->version + 1 : 1;
        $session->save();
        if ($resourceChanged) {
            foreach ($bookings as $booking) {
                (new OutboxEvent)->forceFill([
                    'booking_id' => $booking->id, 'class_session_id' => $session->id, 'recipient_user_id' => $booking->user_id,
                    'event_type' => 'session.resources_changed', 'aggregate_version' => $session->version,
                    'deduplication_key' => "session:{$session->id}:{$session->version}:booking:{$booking->id}:resources",
                    'payload' => ['before' => $before, 'after' => $session->only(['instructor_profile_id', 'room_id'])], 'available_at' => now(),
                ])->save();
            }
        }
        $this->audit($actor, 'schedule.session_saved', $session->id, ['before' => $before, 'after' => $session->only(['starts_at', 'ends_at', 'training_type_id', 'instructor_profile_id', 'room_id', 'capacity', 'status'])]);
    }

    /** @param array<string, mixed> $data */
    private function updateFuture(User $actor, Tenant $tenant, ClassSession $session, array $data): void
    {
        if (! $session->exists || $session->schedule_series_id === null || $session->starts_at->lte(now()) || ! in_array($session->status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['scope' => 'Odaberite budući aktivni termin serije.']);
        }
        $series = ScheduleSeries::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($session->schedule_series_id);
        $date = $session->occurrence_key;
        if ($series->archived_at !== null || substr($data['local_start'], 0, 10) !== $date || $data['status'] === 'cancelled') {
            throw ValidationException::withMessages(['scope' => 'Za buduće termine zadržite datum pojavljivanja; otkaz radite pojedinačno. Zatvorena serija se ne mijenja.']);
        }
        if (isset($data['version']) && (int) $data['version'] !== $session->version) {
            throw ValidationException::withMessages(['version' => 'Termin je izmijenjen. Osvježite raspored prije spremanja.']);
        }
        $this->resources($tenant, $data);
        $successor = $series->replicate();
        $successor->starts_on = CarbonImmutable::parse($date);
        $successor->forceFill(collect($data)->only(['training_type_id', 'instructor_profile_id', 'room_id', 'duration_minutes', 'capacity'])->all());
        $successor->local_start_time = substr($data['local_start'], 11).':00';
        $successor->save();
        if ($date === $series->starts_on->format('Y-m-d')) {
            $series->archived_at = now();
        } else {
            $series->ends_on = CarbonImmutable::parse($date)->subDay();
        }
        $series->save();
        $sessions = $series->sessions()->where('tenant_id', $tenant->id)->where('occurrence_key', '>=', $date)->orderBy('id')->lockForUpdate()->get();
        foreach ($sessions as $item) {
            $item->schedule_series_id = $successor->id;
            $item->save();
        }
        /** Release old intervals within this transaction before validating the whole batch. */
        $editable = $sessions->filter(fn (ClassSession $item): bool => in_array($item->status, ['draft', 'published'], true) && $item->starts_at->gt(now()) && ! $item->bookings()->where('tenant_id', $tenant->id)->where('status', 'confirmed')->exists());
        ClassSession::query()->where('tenant_id', $tenant->id)->whereIn('id', $editable->modelKeys())->update(['status' => 'cancelled']);
        foreach ($editable as $item) {
            $status = $item->status;
            $item->status = 'cancelled';
            $item->syncOriginalAttribute('status');
            $item->status = $status;
            $this->writeSession($actor, $tenant, $item, [...$data, 'version' => $item->version, 'local_start' => $item->occurrence_key.' '.substr($data['local_start'], 11), 'utc_offset' => $item->id === $session->id ? ($data['utc_offset'] ?? null) : null], $series->timezone);
        }
        $this->audit($actor, 'schedule.series_split', $series->id, ['successor_id' => $successor->id, 'from' => $date, 'updated' => $editable->count()]);
    }

    private function ruleVersion(Tenant $tenant): int
    {
        $id = BookingRuleVersion::query()->where('tenant_id', $tenant->id)->orderByDesc('version')->value('id');
        if ($id === null) {
            throw ValidationException::withMessages(['rules' => 'Owner prvo treba spremiti pravila rezervacije u katalogu.']);
        }

        return (int) $id;
    }

    private function lockTenant(User $actor): Tenant
    {
        $tenant = Tenant::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->requireTenant()->id);
        abort_unless($tenant->status === 'active', 404);
        Gate::forUser($actor->fresh() ?? $actor)->authorize('studio-catalog');

        return $tenant;
    }

    /** @param array<string, mixed> $changes */
    private function audit(User $actor, string $action, int $id, array $changes = []): void
    {
        $audit = new TenantAuditLog(['action' => $action, 'subject_type' => str_contains($action, 'session') ? 'class_sessions' : 'schedule_series', 'subject_id' => $id, 'changes' => $changes]);
        $audit->actor()->associate($actor);
        $audit->save();
    }
}
