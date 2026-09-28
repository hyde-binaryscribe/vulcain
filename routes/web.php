<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\VitrineController;
use Illuminate\Support\Facades\Route;

// Aiguilleur de racine (tous hôtes) : vitrine / app / Desk selon l'hôte.
Route::get('/', HomeController::class)->name('home');

// Prévisualisation vitrine (accès direct).
Route::get('/accueil', [VitrineController::class, 'home'])->name('vitrine.home');

// ————————————————————————————————————————————————————————————————
// ESPACE CLIENT — hôte applicatif (app.vulkain.eu) UNIQUEMENT.
// Un client n'accède jamais à autre chose que l'application.
// ————————————————————————————————————————————————————————————————
Route::middleware('app_host')->group(function () {
    // Inscription self-service (bloquée si déjà connecté via « central »).
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
});

// ————————————————————————————————————————————————————————————————
// DESK (management) — hôte de management (desk.vulkain.eu) UNIQUEMENT.
// ————————————————————————————————————————————————————————————————
Route::middleware('desk_host')->group(function () {
    require __DIR__.'/platform.php';
});
