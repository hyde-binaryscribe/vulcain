<?php

use App\Http\Controllers\Api\V1\CheckController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
| API v1 — liens avec Itineris (docs/LIEN-ITINERIS.md) et intra-Chatel
| (docs/LIEN-INTRA-CHATEL.md).
| Authentification par jeton d'organisation (Bearer), sans session ni cookie.
*/
Route::prefix('v1')
    ->middleware([AuthenticateApiToken::class, 'throttle:120,1'])
    ->group(function () {
        Route::get('vehicles', [VehicleController::class, 'index']);
        Route::get('vehicles/checks', [CheckController::class, 'index']);
        Route::get('vehicles/{uuid}', [VehicleController::class, 'show'])->whereUuid('uuid');

        // Prises et fins de service (intra-Chatel).
        Route::get('services', [ServiceController::class, 'index']);
        Route::get('services/current', [ServiceController::class, 'current']);
        Route::post('services/open', [ServiceController::class, 'open']);
        Route::post('services/close', [ServiceController::class, 'close']);
    });
