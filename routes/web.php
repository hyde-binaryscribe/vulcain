<?php

use App\Http\Controllers\DashboardController;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/tenant.php';
require __DIR__.'/platform.php';
