<?php

namespace App\Actions;

use App\DesignRegistry;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\TenantContext;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ManagePlatformTenant
{
    public function __construct(private TenantContext $context) {}

    /** @param array{name: string, design_key: string, hostname: string, owner_email: string, short_description?: ?string, email?: ?string, phone?: ?string, address?: ?string} $data */
    public function create(PlatformAdmin $actor, array $data): Tenant
    {
        Gate::forUser($actor)->authorize('create', Tenant::class);
        Validator::make($data, ['design_key' => ['required', Rule::in(array_keys(app(DesignRegistry::class)->options()))]])->validate();

        try {
            return DB::transaction(function () use ($actor, $data): Tenant {
                $tenant = Tenant::create(['name' => $data['name'], 'design_key' => $data['design_key'], 'status' => 'pending']);
                $this->withinTenant($tenant, function () use ($actor, $tenant, $data): void {
                    TenantProfile::create(collect($data)->only(['name', 'short_description', 'email', 'phone', 'address'])->all());
                    $domain = new TenantDomain(['hostname' => $data['hostname']]);
                    $domain->is_primary = true;
                    $domain->is_active = true;
                    $domain->verification_status = 'pending';
                    $domain->save();
                    $invitation = new StaffInvitation(['email' => $data['owner_email'], 'role' => 'owner']);
                    $invitation->invited_by_platform_admin_id = $actor->id;
                    $invitation->token_hash = hash('sha256', Str::random(64));
                    $invitation->expires_at = now()->addDays(7);
                    $invitation->save();
                    $this->audit($actor, $tenant, 'tenant.created', 'tenant', $tenant->id, [
                        'name' => $tenant->name, 'status' => 'pending', 'design_key' => $tenant->design_key,
                        'hostname' => $domain->hostname, 'owner_invitation_id' => $invitation->id,
                    ]);
                });

                return $tenant;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['hostname' => 'Domena je već dodijeljena studiju.']);
        }
    }

    public function changeDesign(PlatformAdmin $actor, Tenant $tenant, string $designKey): void
    {
        Gate::forUser($actor)->authorize('update', $tenant);
        Validator::make(['design_key' => $designKey], ['design_key' => ['required', Rule::in(array_keys(app(DesignRegistry::class)->options()))]])->validate();
        DB::transaction(function () use ($actor, $tenant, $designKey): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $before = $tenant->design_key;
            $tenant->update(['design_key' => $designKey]);
            if ($before !== $designKey) {
                $this->audit($actor, $tenant, 'tenant.design_changed', 'tenant', $tenant->id, ['before' => $before, 'after' => $designKey]);
            }
        });
    }

    public function addDomain(PlatformAdmin $actor, Tenant $tenant, string $hostname): void
    {
        Gate::forUser($actor)->authorize('update', $tenant);
        try {
            $hostname = app(NormalizeHostname::class)->handle($hostname);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['hostname' => 'Unesite valjani hostname bez protokola, porta i putanje.']);
        }
        Validator::make(['hostname' => $hostname], ['hostname' => [Rule::notIn([config('tenancy.platform_domain')]), Rule::unique('tenant_domains', 'normalized_hostname')]])->validate();
        try {
            DB::transaction(function () use ($actor, $tenant, $hostname): void {
                $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
                $this->withinTenant($tenant, function () use ($actor, $tenant, $hostname): void {
                    $domain = new TenantDomain(['hostname' => $hostname]);
                    $domain->is_primary = ! TenantDomain::query()->where('is_primary', true)->exists();
                    $domain->is_active = true;
                    $domain->verification_status = 'pending';
                    $domain->save();
                    $this->audit($actor, $tenant, 'domain.created', 'tenant_domain', $domain->id, ['hostname' => $hostname, 'verification_status' => 'pending']);
                });
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['hostname' => 'Domena je već dodijeljena studiju.']);
        }
    }

    public function verifyDomain(PlatformAdmin $actor, Tenant $tenant, int $domainId, string $reference): void
    {
        Gate::forUser($actor)->authorize('update', $tenant);
        DB::transaction(function () use ($actor, $tenant, $domainId, $reference): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            if ($tenant->status === 'suspended') {
                throw ValidationException::withMessages(['reference' => 'Suspendirani studio nije moguće aktivirati verifikacijom domene.']);
            }
            $this->withinTenant($tenant, function () use ($actor, $tenant, $domainId, $reference): void {
                $domain = TenantDomain::query()->lockForUpdate()->findOrFail($domainId);
                if ($domain->verification_status === 'verified') {
                    return;
                }
                $domain->verification_status = 'verified';
                $domain->verified_at = now();
                $domain->save();
                $before = $tenant->status;
                if ($domain->is_primary && $domain->is_active && $tenant->status === 'pending') {
                    $tenant->update(['status' => 'active']);
                }
                $this->audit($actor, $tenant, 'domain.verified', 'tenant_domain', $domain->id, [
                    'hostname' => $domain->hostname, 'before' => 'pending', 'after' => 'verified',
                    'reference' => $reference, 'tenant_status_before' => $before, 'tenant_status_after' => $tenant->status,
                ]);
            });
        });
    }

    public function suspend(PlatformAdmin $actor, Tenant $tenant, string $reference): void
    {
        Gate::forUser($actor)->authorize('update', $tenant);
        DB::transaction(function () use ($actor, $tenant, $reference): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            if ($tenant->status === 'suspended') {
                return;
            }
            $before = $tenant->status;
            $tenant->update(['status' => 'suspended']);
            $this->audit($actor, $tenant, 'tenant.suspended', 'tenant', $tenant->id, ['before' => $before, 'after' => 'suspended', 'reference' => $reference]);
        });
    }

    /** @param Closure(): void $callback */
    private function withinTenant(Tenant $tenant, Closure $callback): void
    {
        $this->context->setTenant($tenant);
        try {
            $callback();
        } finally {
            $this->context->setPlatform();
        }
    }

    /** @param array<string, int|string> $changes */
    private function audit(PlatformAdmin $actor, Tenant $tenant, string $action, string $subjectType, int $subjectId, array $changes): void
    {
        $audit = new PlatformAuditLog(['action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'changes' => $changes]);
        $audit->actor_platform_admin_id = $actor->id;
        $audit->target_tenant_id = $tenant->id;
        $audit->save();
    }
}
