<?php

namespace App\Http\Controllers;

use App\Actions\ManageStudioCatalog;
use App\Models\BookingRuleVersion;
use App\Models\InstructorProfile;
use App\Models\PackageProduct;
use App\Models\Room;
use App\Models\TrainingType;
use App\Models\User;
use App\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudioCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('studio-catalog');
        $tenantId = app(TenantContext::class)->requireTenant()->id;

        return Inertia::render('studio/Catalog', [
            'trainingTypes' => TrainingType::query()->where('tenant_id', $tenantId)->orderBy('id')->get(['id', 'name', 'mode', 'duration_minutes', 'capacity', 'description', 'archived_at']),
            'rooms' => Room::query()->where('tenant_id', $tenantId)->orderBy('id')->get(['id', 'name', 'capacity', 'archived_at']),
            'instructors' => InstructorProfile::query()->where('tenant_id', $tenantId)->orderBy('id')->get(['id', 'user_id', 'display_name', 'biography', 'archived_at']),
            'staff' => User::query()->where('tenant_id', $tenantId)->whereIn('role', ['owner', 'manager', 'instructor'])->orderBy('name')->orderBy('id')->get(['id', 'name', 'is_active']),
            'packages' => PackageProduct::query()->where('tenant_id', $tenantId)->with('trainingTypes:id')->orderBy('id')->get()
                ->map(fn (PackageProduct $product): array => [
                    ...$product->only(['id', 'name', 'price_cents', 'credits', 'validity_days', 'archived_at']),
                    'training_type_ids' => $product->trainingTypes->modelKeys(),
                ]),
            'rules' => BookingRuleVersion::query()->where('tenant_id', $tenantId)->orderByDesc('version')->first()?->only(['version', ...array_keys(ManageStudioCatalog::RULE_DEFAULTS)]),
            'ruleDefaults' => ManageStudioCatalog::RULE_DEFAULTS,
            'canManagePricing' => Gate::allows('studio-pricing'),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function storeTrainingType(Request $request, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->save($this->actor($request), new TrainingType, $request->all());

        return $this->saved();
    }

    public function updateTrainingType(Request $request, TrainingType $trainingType, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->save($this->actor($request), $trainingType, $request->all());

        return $this->saved();
    }

    public function storeRoom(Request $request, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->save($this->actor($request), new Room, $request->all());

        return $this->saved();
    }

    public function updateRoom(Request $request, Room $room, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->save($this->actor($request), $room, $request->all());

        return $this->saved();
    }

    public function storeInstructor(Request $request, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->save($this->actor($request), new InstructorProfile, $request->all());

        return $this->saved();
    }

    public function updateInstructor(Request $request, InstructorProfile $instructor, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->save($this->actor($request), $instructor, $request->all());

        return $this->saved();
    }

    public function storePackage(Request $request, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->save($this->actor($request), new PackageProduct, $request->all());

        return $this->saved();
    }

    public function updatePackage(Request $request, PackageProduct $package, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->save($this->actor($request), $package, $request->all());

        return $this->saved();
    }

    public function storeRules(Request $request, ManageStudioCatalog $manage): RedirectResponse
    {
        $manage->createRules($this->actor($request), $request->all());

        return to_route('studio.catalog.index')->with('status', 'Nova verzija pravila je spremljena. Postojeće rezervacije zadržavaju svoja pravila.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function saved(): RedirectResponse
    {
        return to_route('studio.catalog.index')->with('status', 'Katalog je spremljen.');
    }
}
