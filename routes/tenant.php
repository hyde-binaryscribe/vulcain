<?php

use App\Http\Controllers\LocationController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialController;
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
        Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::patch('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::put('vehicles/{vehicle}/assignments', [VehicleController::class, 'assignments'])->name('vehicles.assignments');
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
        Route::post('materials', [MaterialController::class, 'store'])->name('materials.store');
        Route::patch('materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
        Route::patch('materials/{material}/status', [MaterialController::class, 'quickStatus'])->name('materials.status');
        Route::delete('materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');

        Route::post('material-categories', [MaterialCategoryController::class, 'store'])->name('material-categories.store');
        Route::delete('material-categories/{category}', [MaterialCategoryController::class, 'destroy'])->name('material-categories.destroy');
    });
});
