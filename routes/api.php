<?php

use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
| API v1 — lien avec Itineris (docs/LIEN-ITINERIS.md).
| Authentification par jeton d'organisation (Bearer), sans session ni cookie.
*/
Route::prefix('v1')
    ->middleware([AuthenticateApiToken::class, 'throttle:120,1'])
    ->group(function () {
        Route::get('vehicles', [VehicleController::class, 'index']);
        Route::get('vehicles/{uuid}', [VehicleController::class, 'show'])->whereUuid('uuid');
    });
