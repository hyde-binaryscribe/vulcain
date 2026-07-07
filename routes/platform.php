<?php

use App\Http\Controllers\Platform\AuthenticatedPlatformSessionController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\OrganisationController;
use Illuminate\Support\Facades\Route;

// Espace exploitant (Desk) — servi uniquement sur le domaine central.
Route::prefix('platform')->middleware('central')->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('login', [AuthenticatedPlatformSessionController::class, 'create'])->name('platform.login');
        Route::post('login', [AuthenticatedPlatformSessionController::class, 'store'])->middleware('throttle:login');
    });

    Route::middleware('auth:platform')->group(function () {
        Route::post('logout', [AuthenticatedPlatformSessionController::class, 'destroy'])->name('platform.logout');

        Route::get('/', [DashboardController::class, 'index'])->name('platform.dashboard');

        Route::get('organisations/create', [OrganisationController::class, 'create'])->name('platform.organisations.create');
        Route::post('organisations', [OrganisationController::class, 'store'])->name('platform.organisations.store');
        Route::post('organisations/{organisation}/toggle', [OrganisationController::class, 'toggle'])->name('platform.organisations.toggle');
    });
});
