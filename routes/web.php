<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VitrineController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Site vitrine (public). Servi sur le domaine racine via HomeController ;
// exposé aussi ici pour un accès direct / prévisualisation.
Route::get('/accueil', [VitrineController::class, 'home'])->name('vitrine.home');

Route::middleware(['tenant', 'auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/tenant.php';
require __DIR__.'/platform.php';
