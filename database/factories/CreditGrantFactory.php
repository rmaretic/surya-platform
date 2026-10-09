<?php

namespace Database\Factories;

use App\Models\CreditGrant;
use App\Models\PackageProduct;
use App\Models\User;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CreditGrant> */
class CreditGrantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'user_id' => User::factory()->for(app(TenantContext::class)->requireTenant()),
            'package_product_id' => PackageProduct::factory(),
            'created_by_user_id' => User::factory()->for(app(TenantContext::class)->requireTenant())->state(['role' => 'owner']),
            'source' => 'demo',
            'granted_at' => '2026-10-09 10:00:00',
            'valid_from' => '2026-10-09 10:00:00',
            'expires_at' => '2026-11-23 11:00:00',
            'credits' => 5,
            'product_snapshot' => ['name' => 'Demo group package', 'price_cents' => 5000, 'currency' => 'EUR', 'credits' => 5, 'validity_days' => 45, 'timezone' => 'Europe/Zagreb'],
            'operation_key' => (string) Str::uuid(),
        ];
    }

    public function externalPurchase(string $reference): static
    {
        return $this->state(fn (): array => ['source' => 'external_purchase', 'source_reference' => $reference]);
    }

    public function complimentary(string $reason): static
    {
        return $this->state(fn (): array => ['source' => 'complimentary', 'reason' => $reason]);
    }
}
