<?php

namespace App\Http\Controllers;

use App\DesignRegistry;
use App\Models\TenantProfile;
use App\PublicStudioMetadata;
use App\PublicStudioProfile;
use App\TenantContext;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PublicStudioController extends Controller
{
    public function __construct(private TenantContext $context, private DesignRegistry $designs) {}

    public function home(): Response
    {
        if ($this->context->isPlatform()) {
            return Inertia::render('Welcome', ['profile' => null]);
        }

        return $this->renderPage('home');
    }

    public function about(): Response
    {
        return $this->renderPage('about');
    }

    private function renderPage(string $page): Response
    {
        $tenant = $this->context->requireTenant();
        try {
            $component = $this->designs->component($tenant->design_key, $page);
        } catch (InvalidArgumentException) {
            Log::warning('Public studio design is not registered.', ['tenant_id' => $tenant->id]);
            abort(503, 'Javna stranica trenutačno nije dostupna.');
        }
        $profile = TenantProfile::query()->first();
        $publicProfile = new PublicStudioProfile(
            $profile->name ?? $tenant->name,
            $profile?->short_description,
            $profile?->email,
            $profile?->phone,
            $profile?->address,
        );

        return Inertia::render($component, [
            'profile' => $publicProfile->toArray(),
            'seo' => app(PublicStudioMetadata::class)->forPage($publicProfile, $page),
        ]);
    }
}
