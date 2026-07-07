<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\StockLot;
use App\Models\Vehicle;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    private const ACTIONS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
    ];

    private const SUBJECTS = [
        Vehicle::class => 'Véhicule',
        Location::class => 'Emplacement',
        Material::class => 'Matériel',
        MaterialItem::class => 'Exemplaire',
        StockLot::class => 'Lot',
    ];

    public function index(): Response
    {
        $logs = ActivityLog::query()
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (ActivityLog $l) => self::format($l));

        return Inertia::render('Activity/Index', [
            'logs' => $logs,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function format(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'actor' => $log->actor_name,
            'action' => self::ACTIONS[$log->action] ?? $log->action,
            'subject_type' => self::SUBJECTS[$log->subject_type] ?? class_basename((string) $log->subject_type),
            'description' => $log->description,
            'changes' => $log->properties,
            'at' => $log->created_at?->format('d/m/Y H:i'),
        ];
    }
}
