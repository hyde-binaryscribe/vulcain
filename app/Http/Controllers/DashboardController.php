<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Domain\Fleet\VehicleStatus;
use App\Models\Event;
use App\Models\Material;
use App\Models\Protocol;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = Carbon::today();
        $soon = $today->copy()->addDays(30);

        $lowStock = Material::query()
            ->where('minimum_qty', '>', 0)
            ->with(['lots:id,material_id,quantity', 'items:id,material_id'])
            ->get()
            ->filter(fn (Material $m) => $m->isBelowThreshold())
            ->count();

        $openEvents = Event::query()
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'vehicles' => Vehicle::query()->count(),
                'vehicles_available' => Vehicle::query()->where('status', VehicleStatus::DISPONIBLE->value)->count(),
                'users' => User::query()->where('is_active', true)->count(),
                'protocols_draft' => Protocol::query()->where('status', Protocol::STATUS_DRAFT)->count(),
                'protocols_total' => Protocol::query()->count(),
            ],
            'alerts' => [
                'expired' => StockLot::query()->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today)->count(),
                'expiring_soon' => StockLot::query()->whereNotNull('expiry_date')->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', $soon)->count(),
                'low_stock' => $lowStock,
                'open_events' => $openEvents,
            ],
        ]);
    }
}
