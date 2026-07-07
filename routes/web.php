<?php

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (TenantContext $tenant) {
    if ($tenant->check()) {
        return Auth::check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }

    // Domaine central : espace exploitant (Desk).
    return redirect()->route('platform.dashboard');
})->name('home');

Route::middleware(['tenant', 'auth'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/platform.php';
