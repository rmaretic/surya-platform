<?php

namespace App\Actions;

use App\Models\PlatformAdmin;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use stdClass;

class ReadOperationalDashboard
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function studio(User $actor, array $input): array
    {
        Gate::forUser($actor)->authorize('studio-administration');
        $tenant = app(TenantContext::class)->requireTenant();
        $period = $this->period($input, $tenant->timezone);
        $sessions = $this->sessions($period)->where('s.tenant_id', $tenant->id);
        if ($actor->role === 'instructor') {
            $sessions->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('instructor_profiles as i')
                ->whereColumn('i.id', 's.instructor_profile_id')->whereColumn('i.tenant_id', 's.tenant_id')
                ->where('i.user_id', $actor->id)->whereNull('i.archived_at'));
        }

        $rows = $this->sessionCounts($sessions);
        $summary = $this->summarize(DB::query()->fromSub($rows, 'r'))->first();
        $page = DB::query()->fromSub($rows, 'r')->orderBy('starts_at')->orderBy('id')->paginate(25);
        $page->through(fn (stdClass $row): array => [
            'id' => (int) $row->id, 'name' => $row->name, 'status' => $row->status,
            'local_start' => CarbonImmutable::parse($row->starts_at, 'UTC')->setTimezone($tenant->timezone)->format('Y-m-d H:i'),
            'capacity' => (int) $row->capacity, 'confirmed' => (int) $row->confirmed,
            'available' => $row->status === 'cancelled' ? null : max(0, (int) $row->capacity - (int) $row->confirmed),
            'pending' => (int) $row->pending, 'present' => (int) $row->present,
            'no_show' => (int) $row->no_show, 'late_cancelled' => (int) $row->late_cancelled,
        ]);

        return ['period' => $period, 'scope' => $actor->role === 'instructor' ? 'own' : 'studio',
            'summary' => $this->metrics($summary), 'sessions' => $page];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function platform(PlatformAdmin $actor, array $input): array
    {
        Gate::forUser($actor)->authorize('platform.analytics.read');
        $period = $this->period($input, 'Europe/Zagreb');
        $rows = $this->sessionCounts($this->sessions($period));
        $totals = $this->summarize(DB::query()->fromSub($rows, 'r'))->first();
        $byTenant = $this->summarize(DB::query()->fromSub($rows, 'r'))->addSelect('tenant_id')->groupBy('tenant_id');
        $tenants = DB::table('tenants as t')->leftJoinSub($byTenant, 'm', 'm.tenant_id', '=', 't.id')
            ->select('t.id', 't.name', 't.status', 'm.sessions', 'm.cancelled', 'm.capacity', 'm.confirmed', 'm.pending', 'm.present', 'm.no_show', 'm.late_cancelled')
            ->orderBy('t.id')->paginate(25);
        $tenants->through(fn (stdClass $row): array => [
            'id' => (int) $row->id, 'name' => $row->name, 'status' => $row->status, 'metrics' => $this->metrics($row),
        ]);

        return ['period' => $period, 'summary' => $this->metrics($totals), 'tenants' => $tenants];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{from: string, to: string, timezone: string}
     */
    private function period(array $input, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $data = Validator::make($input, [
            'from' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ])->validate();
        $from = CarbonImmutable::parse($data['from'] ?? $today->format('Y-m-d'), $timezone);
        $to = CarbonImmutable::parse($data['to'] ?? $from->addDays(6)->format('Y-m-d'), $timezone);
        if ($to->lt($from) || $from->diffInDays($to) > 365) {
            throw ValidationException::withMessages(['to' => 'Odaberite završetak nakon početka, za najviše 366 dana.']);
        }

        return ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'timezone' => $timezone];
    }

    /** @param array{from: string, to: string, timezone: string} $period */
    private function sessions(array $period): Builder
    {
        return DB::table('class_sessions as s')
            ->where('s.starts_at', '>=', CarbonImmutable::parse($period['from'], $period['timezone'])->utc())
            ->where('s.starts_at', '<', CarbonImmutable::parse($period['to'], $period['timezone'])->addDay()->utc())
            ->whereIn('s.status', ['published', 'completed', 'cancelled']);
    }

    private function sessionCounts(Builder $sessions): Builder
    {
        return (clone $sessions)
            ->join('training_types as tt', fn (JoinClause $join) => $join->on('tt.id', '=', 's.training_type_id')->on('tt.tenant_id', '=', 's.tenant_id'))
            ->leftJoin('bookings as b', fn (JoinClause $join) => $join->on('b.class_session_id', '=', 's.id')->on('b.tenant_id', '=', 's.tenant_id'))
            ->leftJoin('attendance_records as a', fn (JoinClause $join) => $join->on('a.booking_id', '=', 'b.id')->on('a.tenant_id', '=', 'b.tenant_id'))
            ->select('s.id', 's.tenant_id', 's.starts_at', 's.status', 's.capacity', 'tt.name')
            ->selectRaw("SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed")
            ->selectRaw("SUM(CASE WHEN b.status = 'confirmed' AND COALESCE(a.status, 'pending') = 'pending' THEN 1 ELSE 0 END) AS pending")
            ->selectRaw("SUM(CASE WHEN b.status = 'confirmed' AND a.status = 'present' THEN 1 ELSE 0 END) AS present")
            ->selectRaw("SUM(CASE WHEN b.status = 'confirmed' AND a.status = 'no_show' THEN 1 ELSE 0 END) AS no_show")
            ->selectRaw("SUM(CASE WHEN b.status = 'cancelled' AND b.cancellation_reason = 'customer_late' THEN 1 ELSE 0 END) AS late_cancelled")
            ->groupBy('s.id', 's.tenant_id', 's.starts_at', 's.status', 's.capacity', 'tt.name');
    }

    private function summarize(Builder $query): Builder
    {
        $query->selectRaw("SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END) AS sessions")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled")
            ->selectRaw('SUM(late_cancelled) AS late_cancelled');
        foreach (['capacity', 'confirmed', 'pending', 'present', 'no_show'] as $metric) {
            $query->selectRaw("SUM(CASE WHEN status <> 'cancelled' THEN {$metric} ELSE 0 END) AS {$metric}");
        }

        return $query;
    }

    /** @return array{sessions: int, cancelled: int, capacity: int, confirmed: int, available: int, pending: int, present: int, no_show: int, late_cancelled: int, occupancy: float|null} */
    private function metrics(?stdClass $row): array
    {
        $capacity = (int) ($row->capacity ?? 0);
        $confirmed = (int) ($row->confirmed ?? 0);

        return ['sessions' => (int) ($row->sessions ?? 0), 'cancelled' => (int) ($row->cancelled ?? 0),
            'capacity' => $capacity, 'confirmed' => $confirmed, 'available' => max(0, $capacity - $confirmed),
            'pending' => (int) ($row->pending ?? 0), 'present' => (int) ($row->present ?? 0),
            'no_show' => (int) ($row->no_show ?? 0), 'late_cancelled' => (int) ($row->late_cancelled ?? 0),
            'occupancy' => $capacity > 0 ? round(100 * $confirmed / $capacity, 1) : null];
    }
}
