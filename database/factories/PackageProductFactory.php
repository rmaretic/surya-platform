<?php

namespace Database\Factories;

use App\Models\PackageProduct;
use App\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PackageProduct> */
class PackageProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => app(TenantContext::class)->requireTenant()->id,
            'name' => 'Demo group package',
            'price_cents' => 5000,
            'currency' => 'EUR',
            'credits' => 5,
            'validity_days' => 45,
        ];
    }
}
