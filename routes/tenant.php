<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialItemController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\ProtocolTemplateController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockLotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

// Routes métier d'une organisation (sous-domaine résolu + authentification).
Route::middleware(['tenant', 'auth'])->group(function () {

    // Administration des utilisateurs.
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users/invite', [UserController::class, 'store'])->name('users.invite');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('invitations/{invitation}', [UserController::class, 'cancelInvitation'])->name('invitations.cancel');
        Route::post('invitations/{invitation}/resend', [UserController::class, 'resendInvitation'])->name('invitations.resend');
    });

    // Véhicules + affectations.
    Route::middleware('permission:vehicles.manage')->group(function () {
        Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
        Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::patch('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::put('vehicles/{vehicle}/assignments', [VehicleController::class, 'assignments'])->name('vehicles.assignments');
    });

    // Historique des actions (journal d'activité).
    Route::middleware('permission:history.view')->group(function () {
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    });

    // Pharmacie : déclaration rapide des consommables.
    Route::middleware('permission:pharmacy.manage')->group(function () {
        Route::get('pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
        Route::post('pharmacy/consumables', [PharmacyController::class, 'store'])->name('pharmacy.consumables.store');
    });

    // Événements (anomalies / réparations) — tableau Kanban.
    Route::middleware('permission:anomalies.manage')->group(function () {
        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::post('events', [EventController::class, 'store'])->name('events.store');
        Route::patch('events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::patch('events/{event}/move', [EventController::class, 'move'])->name('events.move');
        Route::post('events/{event}/comments', [EventController::class, 'comment'])->name('events.comment');
        Route::patch('events/{event}/material-status', [EventController::class, 'materialStatus'])->name('events.material-status');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    });

    // Réglages de l'organisation (propriétaire / administrateur).
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    // Modèles de protocole.
    Route::middleware('permission:templates.manage')->group(function () {
        Route::get('templates', [ProtocolTemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [ProtocolTemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [ProtocolTemplateController::class, 'edit'])->name('templates.edit');
        Route::patch('templates/{template}', [ProtocolTemplateController::class, 'update'])->name('templates.update');
        Route::patch('templates/{template}/exclusions', [ProtocolTemplateController::class, 'exclusions'])->name('templates.exclusions');
        Route::delete('templates/{template}', [ProtocolTemplateController::class, 'destroy'])->name('templates.destroy');
    });

    // Réalisation des protocoles (vérificateur ou gestionnaire).
    Route::middleware('permission:protocols.perform|protocols.manage')->group(function () {
        Route::get('protocols', [ProtocolController::class, 'index'])->name('protocols.index');
        Route::post('protocols', [ProtocolController::class, 'start'])->name('protocols.start');
        Route::get('protocols/{protocol}', [ProtocolController::class, 'show'])->name('protocols.show');
        Route::get('protocols/{protocol}/report', [ProtocolController::class, 'report'])->name('protocols.report');
        Route::patch('protocols/{protocol}/items/{item}', [ProtocolController::class, 'updateItem'])->name('protocols.items.update');
        Route::post('protocols/{protocol}/validate', [ProtocolController::class, 'finalize'])->name('protocols.validate');
    });

    // Emplacements & sous-emplacements.
    Route::middleware('permission:locations.manage')->group(function () {
        Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
        Route::patch('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::post('locations/{location}/toggle', [LocationController::class, 'toggle'])->name('locations.toggle');
        Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
    });

    // Catalogue matériel + catégories.
    Route::middleware('permission:catalog.manage')->group(function () {
        Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
        Route::get('materials/{material}', [MaterialController::class, 'show'])->name('materials.show');
        Route::post('materials', [MaterialController::class, 'store'])->name('materials.store');
        Route::patch('materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
        Route::patch('materials/{material}/status', [MaterialController::class, 'quickStatus'])->name('materials.status');
        Route::patch('materials/{material}/stock', [MaterialController::class, 'setStock'])->name('materials.stock');
        Route::delete('materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');

        // Exemplaires (mode unitaire) et lots (mode consommable).
        Route::post('materials/{material}/items', [MaterialItemController::class, 'store'])->name('material-items.store');
        Route::patch('material-items/{item}', [MaterialItemController::class, 'update'])->name('material-items.update');
        Route::delete('material-items/{item}', [MaterialItemController::class, 'destroy'])->name('material-items.destroy');

        Route::post('materials/{material}/lots', [StockLotController::class, 'store'])->name('stock-lots.store');
        Route::patch('stock-lots/{lot}', [StockLotController::class, 'update'])->name('stock-lots.update');
        Route::delete('stock-lots/{lot}', [StockLotController::class, 'destroy'])->name('stock-lots.destroy');

        Route::post('material-categories', [MaterialCategoryController::class, 'store'])->name('material-categories.store');
        Route::delete('material-categories/{category}', [MaterialCategoryController::class, 'destroy'])->name('material-categories.destroy');
    });
});
