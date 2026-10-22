<?php

namespace App\Http\Controllers;

use App\Actions\ReadOperationalDashboard;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    public function index(Request $request, ReadOperationalDashboard $dashboard): Response
    {
        $actor = $request->user('platform');
        abort_unless($actor instanceof PlatformAdmin, 403);

        return Inertia::render('platform/Dashboard', $dashboard->platform($actor, $request->only(['from', 'to', 'page'])));
    }
}
