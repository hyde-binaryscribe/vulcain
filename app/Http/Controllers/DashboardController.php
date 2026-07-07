<?php

namespace App\Http\Controllers;

use App\Domain\Fleet\VehicleStatus;
use App\Models\Protocol;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'vehicles' => Vehicle::query()->count(),
                'vehicles_available' => Vehicle::query()->where('status', VehicleStatus::DISPONIBLE->value)->count(),
                'users' => User::query()->where('is_active', true)->count(),
                'protocols_draft' => Protocol::query()->where('status', Protocol::STATUS_DRAFT)->count(),
                'protocols_total' => Protocol::query()->count(),
            ],
        ]);
    }
}
