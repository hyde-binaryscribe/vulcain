<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __invoke(TenantContext $tenant): RedirectResponse
    {
        if ($tenant->check()) {
            return Auth::check()
                ? redirect()->route('dashboard')
                : redirect()->route('login');
        }

        // Domaine central : espace exploitant (Desk).
        return redirect()->route('platform.dashboard');
    }
}
