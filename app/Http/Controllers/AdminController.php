<?php

namespace App\Http\Controllers;

use App\Models\DisinfectionProtocol;
use App\Models\MaterialCategory;
use App\Models\MaterialType;
use App\Models\ProtocolTemplate;
use App\Models\ServiceProtocol;
use App\Models\User;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel d'administration (paramétrage de l'organisation) : page d'accueil en
 * cartes. Les pages de gestion elles-mêmes gardent leurs routes existantes ;
 * ce panel les regroupe derrière une seule entrée de menu.
 */
class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Index', [
            'counts' => [
                'vehicle_types' => VehicleType::query()->count(),
                'vehicle_models' => VehicleModel::query()->count(),
                'service_protocols' => ServiceProtocol::query()->count(),
                'disinfection_protocols' => DisinfectionProtocol::query()->count(),
                'material_types' => MaterialType::query()->count(),
                'material_categories' => MaterialCategory::query()->count(),
                'protocol_templates' => ProtocolTemplate::query()->count(),
                'users' => User::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
