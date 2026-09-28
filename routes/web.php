<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\VitrineController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Site vitrine (public). Servi sur le domaine racine via HomeController ;
// exposé aussi ici pour un accès direct / prévisualisation.
Route::get('/accueil', [VitrineController::class, 'home'])->name('vitrine.home');

// Inscription self-service (hôtes centraux uniquement — jamais sur un tenant).
Route::middleware('central')->group(function () {
    Route::get('/inscription', [RegistrationController::class, 'create'])->name('register');
    Route::post('/inscription', [RegistrationController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Fin d'incarnation (support Desk) — retour vers le Desk.
    Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
});

require __DIR__.'/auth.php';
require __DIR__.'/tenant.php';
require __DIR__.'/platform.php';
