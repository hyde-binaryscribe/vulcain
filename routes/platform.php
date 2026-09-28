<?php

use App\Http\Controllers\Platform\AuthenticatedPlatformSessionController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\GroupController;
use App\Http\Controllers\Platform\InsightsController;
use App\Http\Controllers\Platform\OrganisationController;
use App\Http\Controllers\Platform\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Espace exploitant (Desk). Protégé par le guard « platform » (séparé du guard
// métier « web »). On n'applique PAS le middleware « central » ici : en mode
// hôte unique, une session web peut coexister dans le navigateur (impersonation,
// ou app ouverte sur le même domaine parent) ; « central » ferait alors un 404
// sur tout le Desk. Le guard plateforme suffit à protéger ces routes.
Route::prefix('platform')->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('login', [AuthenticatedPlatformSessionController::class, 'create'])->name('platform.login');
        Route::post('login', [AuthenticatedPlatformSessionController::class, 'store'])->middleware('throttle:login');
    });

    Route::middleware('auth:platform')->group(function () {
        Route::post('logout', [AuthenticatedPlatformSessionController::class, 'destroy'])->name('platform.logout');

        Route::get('/', [DashboardController::class, 'index'])->name('platform.dashboard');

        // Vues transverses : utilisateurs & activité de la plateforme.
        Route::get('users', [InsightsController::class, 'users'])->name('platform.users');
        Route::get('activity', [InsightsController::class, 'activity'])->name('platform.activity');

        // Groupes / multi-entreprises (exploitant global uniquement — vérifié dans le contrôleur).
        Route::get('groups', [GroupController::class, 'index'])->name('platform.groups');
        Route::post('groups', [GroupController::class, 'store'])->name('platform.groups.store');
        Route::patch('organisations/{organisation}/group', [GroupController::class, 'assign'])->name('platform.organisations.group');
        Route::post('groups/{group}/managers', [GroupController::class, 'createManager'])->name('platform.groups.managers');

        Route::get('organisations/create', [OrganisationController::class, 'create'])->name('platform.organisations.create');
        Route::post('organisations', [OrganisationController::class, 'store'])->name('platform.organisations.store');
        Route::get('organisations/{organisation}', [OrganisationController::class, 'show'])->name('platform.organisations.show');
        Route::post('organisations/{organisation}/toggle', [OrganisationController::class, 'toggle'])->name('platform.organisations.toggle');

        // Outils de support.
        Route::post('organisations/{organisation}/resend-invitation', [OrganisationController::class, 'resendInvitation'])->name('platform.organisations.resend');
        Route::post('organisations/{organisation}/credentials', [OrganisationController::class, 'generateAdminCredentials'])->name('platform.organisations.credentials');
        Route::post('organisations/{organisation}/impersonate', [OrganisationController::class, 'impersonate'])->name('platform.organisations.impersonate');

        // Gestion de l'abonnement d'une organisation.
        Route::get('organisations/{organisation}/subscription', [SubscriptionController::class, 'show'])->name('platform.organisations.subscription');
        Route::patch('organisations/{organisation}/subscription', [SubscriptionController::class, 'update'])->name('platform.organisations.subscription.update');
    });
});
