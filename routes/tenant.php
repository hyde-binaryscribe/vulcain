<?php

use App\Http\Controllers\UserController;
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
});
