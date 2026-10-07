<?php

namespace App\Http\Controllers;

use App\Actions\ListPlatformTenants;
use App\Actions\ManagePlatformTenant;
use App\Actions\SendOwnerInvitation;
use App\DesignRegistry;
use App\Http\Requests\StorePlatformTenantRequest;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTenantController extends Controller
{
    public function index(Request $request, ListPlatformTenants $list): Response
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('viewAny', Tenant::class);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $counts = Tenant::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('platform/Tenants', [
            'tenants' => $list->handle($actor, $data['search'] ?? ''),
            'search' => $data['search'] ?? '',
            'counts' => ['pending' => (int) ($counts['pending'] ?? 0), 'active' => (int) ($counts['active'] ?? 0), 'suspended' => (int) ($counts['suspended'] ?? 0)],
            'designs' => app(DesignRegistry::class)->options(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::forUser($this->actor($request))->authorize('create', Tenant::class);

        return Inertia::render('platform/CreateTenant', ['designs' => app(DesignRegistry::class)->options()]);
    }

    public function store(StorePlatformTenantRequest $request, ManagePlatformTenant $manage): RedirectResponse
    {
        $tenant = $manage->create($this->actor($request), $request->creationData());

        return to_route('platform.tenants.show', $tenant);
    }

    public function show(Request $request, Tenant $tenant): Response
    {
        Gate::forUser($this->actor($request))->authorize('view', $tenant);

        return Inertia::render('platform/TenantDetail', [
            'tenant' => $tenant->only(['id', 'name', 'status', 'design_key']),
            'profile' => $tenant->profile()->withoutGlobalScope(TenantScope::class)->first()?->only(['name', 'short_description', 'email', 'phone', 'address']),
            'domains' => $tenant->domains()->withoutGlobalScope(TenantScope::class)->orderBy('id')
                ->get(['id', 'hostname', 'verification_status', 'verified_at', 'is_primary', 'is_active']),
            'invitation' => $tenant->invitations()->withoutGlobalScope(TenantScope::class)->where('role', 'owner')
                ->whereNotNull('invited_by_platform_admin_id')->latest('id')->first()
                ?->only(['id', 'email', 'expires_at', 'accepted_at', 'revoked_at', 'sent_at', 'delivery_failed_at']),
            'audits' => PlatformAuditLog::query()->where('target_tenant_id', $tenant->id)->with('actor:id,name')
                ->latest('id')->limit(30)->get()->map(fn (PlatformAuditLog $audit): array => [
                    'id' => $audit->id, 'action' => $audit->action, 'subject_type' => $audit->subject_type,
                    'subject_id' => $audit->subject_id, 'changes' => $audit->changes,
                    'created_at' => $audit->created_at->toIso8601String(), 'actor' => $audit->actor?->name,
                ]),
            'designs' => app(DesignRegistry::class)->options(),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request, Tenant $tenant, ManagePlatformTenant $manage): RedirectResponse
    {
        Gate::forUser($this->actor($request))->authorize('update', $tenant);
        $data = $request->validate(['design_key' => ['required', 'string']]);
        $manage->changeDesign($this->actor($request), $tenant, $data['design_key']);

        return to_route('platform.tenants.show', $tenant)->with('status', 'Dizajn je spremljen.');
    }

    public function addDomain(Request $request, Tenant $tenant, ManagePlatformTenant $manage): RedirectResponse
    {
        Gate::forUser($this->actor($request))->authorize('update', $tenant);
        $data = $request->validate(['hostname' => ['required', 'string', 'max:253']]);
        $manage->addDomain($this->actor($request), $tenant, $data['hostname']);

        return to_route('platform.tenants.show', $tenant)->with('status', 'Domena čeka operativnu verifikaciju.');
    }

    public function verifyDomain(Request $request, Tenant $tenant, int $domain, ManagePlatformTenant $manage): RedirectResponse
    {
        Gate::forUser($this->actor($request))->authorize('update', $tenant);
        $data = $request->validate(['reference' => $this->referenceRules(), 'confirmed' => ['accepted']]);
        $manage->verifyDomain($this->actor($request), $tenant, $domain, $data['reference']);

        return to_route('platform.tenants.show', $tenant)->with('status', 'Verifikacija domene je evidentirana.');
    }

    public function suspend(Request $request, Tenant $tenant, ManagePlatformTenant $manage): RedirectResponse
    {
        Gate::forUser($this->actor($request))->authorize('update', $tenant);
        $data = $request->validate(['reference' => $this->referenceRules(), 'confirmed' => ['accepted']]);
        $manage->suspend($this->actor($request), $tenant, $data['reference']);

        return to_route('platform.tenants.show', $tenant)->with('status', 'Studio je suspendiran. Podaci su sačuvani.');
    }

    public function sendInvitation(Request $request, Tenant $tenant, SendOwnerInvitation $send): RedirectResponse
    {
        $sent = $send->handle($this->actor($request), $tenant);

        return to_route('platform.tenants.show', $tenant)->with('status', $sent
            ? 'Poziv je predan email transportu. Vrijedi sedam dana; prethodni poziv više ne vrijedi.'
            : 'Slanje nije uspjelo. Provjerite email transport pa ponovite poziv.');
    }

    private function actor(Request $request): PlatformAdmin
    {
        $actor = $request->user('platform');
        abort_unless($actor instanceof PlatformAdmin, 403);

        return $actor;
    }

    /** @return list<string> */
    private function referenceRules(): array
    {
        return ['required', 'string', 'max:120', 'regex:/^[\pL\pN ._\/:#-]+$/u'];
    }
}
