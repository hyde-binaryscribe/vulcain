<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TelematicsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\VitrineController;
use Illuminate\Support\Facades\Route;

// Aiguilleur de racine (tous hôtes) : vitrine / app / Desk selon l'hôte.
Route::get('/', HomeController::class)->name('home');

// Prévisualisation vitrine (accès direct).
Route::get('/accueil', [VitrineController::class, 'home'])->name('vitrine.home');

// Déclencheur de tâches planifiées par URL (jeton secret), tous hôtes — pour les
// hébergements sans PHP CLI dans le cron (tâche Plesk « Récupérer une URL »).
// Deux formes : jeton en query (?token=) ou dans le chemin (/{token}), cette
// dernière évitant les blocages de certains outils de fetch / WAF sur « ? ».
Route::get('/cron/echeances', [CronController::class, 'echeances'])
    ->middleware('throttle:12,1')->name('cron.echeances');
Route::get('/cron/echeances/{token}', [CronController::class, 'echeances'])
    ->middleware('throttle:12,1')->name('cron.echeances.token');

// Ingestion télématique (positions transférées par Traccar), tous hôtes.
// Jeton dans le chemin ou en query. Débit élevé (une position par véhicule
// toutes les quelques secondes en flotte).
Route::post('/ingest/traccar/{token?}', [TelematicsController::class, 'ingest'])
    ->middleware('throttle:600,1')->name('ingest.traccar');

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
