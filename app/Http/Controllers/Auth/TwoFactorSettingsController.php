<?php

namespace App\Http\Controllers\Auth;

use App\Auth\AdministrativeAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('auth/TwoFactorSettings', [
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'twoFactorRequired' => AdministrativeAccess::requiresTwoFactor($request->user()),
        ]);
    }
}
