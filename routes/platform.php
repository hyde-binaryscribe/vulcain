<?php

use App\Http\Controllers\Platform\AuthenticatedPlatformSessionController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\GroupController;
use App\Http\Controllers\Platform\OrganisationController;
use App\Http\Controllers\Platform\SubscriptionController;
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

        // Groupes / multi-entreprises (exploitant global uniquement — vérifié dans le contrôleur).
        Route::get('groups', [GroupController::class, 'index'])->name('platform.groups');
        Route::post('groups', [GroupController::class, 'store'])->name('platform.groups.store');
        Route::patch('organisations/{organisation}/group', [GroupController::class, 'assign'])->name('platform.organisations.group');
        Route::post('groups/{group}/managers', [GroupController::class, 'createManager'])->name('platform.groups.managers');

        Route::get('organisations/create', [OrganisationController::class, 'create'])->name('platform.organisations.create');
        Route::post('organisations', [OrganisationController::class, 'store'])->name('platform.organisations.store');
        Route::post('organisations/{organisation}/toggle', [OrganisationController::class, 'toggle'])->name('platform.organisations.toggle');

        // Gestion de l'abonnement d'une organisation.
        Route::get('organisations/{organisation}/subscription', [SubscriptionController::class, 'show'])->name('platform.organisations.subscription');
        Route::patch('organisations/{organisation}/subscription', [SubscriptionController::class, 'update'])->name('platform.organisations.subscription.update');
    });
});
