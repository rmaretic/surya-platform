<?php

use App\Models\AvailabilityRule;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\CreditGrant;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\TenantContext;

/** @return array{Tenant, User, ClassSession, CreditGrant} */
function bookingSetup(): array
{
    $tenant = Tenant::factory()->active()->create();
    app(TenantContext::class)->setTenant($tenant);
    TenantDomain::factory()->verified()->for($tenant)->create(['hostname' => 'booking-'.$tenant->id.'.yoga.test', 'is_primary' => true]);
    $customer = User::factory()->for($tenant)->create(['role' => 'customer']);
    $session = ClassSession::factory()->create(['status' => 'published', 'starts_at' => '2026-10-19 10:00:00', 'ends_at' => '2026-10-19 11:00:00']);
    $grant = bookingGrant($customer, $session->training_type_id);

    return [$tenant, $customer, $session, $grant];
}

function bookingGrant(User $customer, int $typeId, int $credits = 1, array $overrides = []): CreditGrant
{
    $grant = CreditGrant::factory()->for($customer)->create(['credits' => $credits, ...$overrides]);
    $grant->trainingTypes()->attach($typeId);
    CreditEntry::factory()->for($grant, 'grant')->create(['amount' => $credits]);

    return $grant;
}

function bookingPrivate(User $customer): AvailabilityRule
{
    $rule = AvailabilityRule::factory()->create();
    bookingGrant($customer, $rule->training_type_id);

    return $rule;
}
