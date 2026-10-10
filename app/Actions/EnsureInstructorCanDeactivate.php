<?php

namespace App\Actions;

use App\Models\AvailabilityRule;
use App\Models\InstructorProfile;
use App\Models\ScheduleSeries;
use App\TenantContext;
use Illuminate\Validation\ValidationException;

class EnsureInstructorCanDeactivate
{
    /** Call inside the tenant coordination transaction, before changing the profile or user. */
    public function handle(InstructorProfile $profile, string $field = 'is_active'): void
    {
        $today = now()->setTimezone(app(TenantContext::class)->requireTenant()->timezone)->toDateString();
        $hasSessions = $profile->sessions()->whereIn('status', ['draft', 'published'])->where('ends_at', '>', now())->exists();
        $hasSeries = ScheduleSeries::query()->where('instructor_profile_id', $profile->id)->whereNull('archived_at')
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today))->exists();
        $hasAvailability = AvailabilityRule::query()->where('instructor_profile_id', $profile->id)->whereNull('archived_at')
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today))->exists();

        if ($hasSessions || $hasSeries || $hasAvailability) {
            throw ValidationException::withMessages([$field => 'Prije deaktivacije zamijenite instruktora ili otkažite njegove buduće termine te zatvorite aktivne serije i dostupnost.']);
        }
    }
}
