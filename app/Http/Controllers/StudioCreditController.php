<?php

namespace App\Http\Controllers;

use App\Actions\CreditLedger;
use App\Actions\ManageStudioCredits;
use App\Models\CreditGrant;
use App\Models\PackageProduct;
use App\Models\User;
use App\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudioCreditController extends Controller
{
    public function index(Request $request, CreditLedger $ledger): Response
    {
        Gate::authorize('studio-credits');
        $tenant = app(TenantContext::class)->requireTenant();
        $grants = CreditGrant::query()->where('tenant_id', $tenant->id)
            ->with(['user:id,name', 'creator:id,name', 'trainingTypes:id,name', 'entries' => fn ($query) => $query->orderByDesc('id')])
            ->orderByDesc('id')->paginate(20);
        $totals = $ledger->balances($grants->getCollection());

        return Inertia::render('studio/Credits', [
            'grants' => $grants->through(fn (CreditGrant $grant): array => [
                ...$grant->only(['id', 'source', 'source_reference', 'reason', 'credits']),
                'name' => $grant->product_snapshot['name'],
                'customer' => $grant->user->name, 'author' => $grant->creator->name,
                'granted_at' => CarbonImmutable::parse($grant->granted_at)->setTimezone($tenant->timezone)->format('d.m.Y. H:i'),
                'valid_from' => CarbonImmutable::parse($grant->valid_from)->setTimezone($tenant->timezone)->format('d.m.Y. H:i'),
                'expires_at' => CarbonImmutable::parse($grant->expires_at)->setTimezone($tenant->timezone)->format('d.m.Y. H:i'),
                'training_types' => $grant->trainingTypes->pluck('name')->all(),
                'totals' => $totals[$grant->id],
                'entries' => $grant->entries->map(fn ($entry): array => [
                    ...$entry->only(['id', 'kind', 'amount', 'reason', 'booking_id']),
                    'occurred_at' => CarbonImmutable::parse($entry->occurred_at)->setTimezone($tenant->timezone)->format('d.m.Y. H:i'),
                ])->all(),
            ]),
            'customers' => User::query()->where('tenant_id', $tenant->id)->where('role', 'customer')->where('is_active', true)->orderBy('name')->orderBy('id')->get(['id', 'name', 'email']),
            'packages' => PackageProduct::query()->where('tenant_id', $tenant->id)->whereNull('archived_at')->orderBy('name')->orderBy('id')->get(['id', 'name', 'credits', 'validity_days']),
            'operationKey' => (string) Str::uuid(), 'timezone' => $tenant->timezone,
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, ManageStudioCredits $manage): RedirectResponse
    {
        $manage->grant($this->actor($request), $request->all());

        return to_route('studio.credits.index')->with('status', 'Pravo je dodijeljeno. Nije evidentirana naplata platforme.');
    }

    public function adjust(Request $request, CreditGrant $grant, ManageStudioCredits $manage): RedirectResponse
    {
        $manage->adjust($this->actor($request), $grant, $request->all());

        return to_route('studio.credits.index')->with('status', 'Korekcija je spremljena u kreditnu povijest.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
