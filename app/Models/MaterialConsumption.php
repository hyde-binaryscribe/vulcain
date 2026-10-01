<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sortie d'un consommable d'un véhicule pendant un service (traçabilité).
 */
class MaterialConsumption extends Model
{
    use BelongsToOrganisation, RecordsActivity;

    protected $fillable = [
        'vehicle_id',
        'material_id',
        'material_item_id',
        'stock_lot_id',
        'vehicle_session_id',
        'user_id',
        'quantity',
        'serial_number',
        'consumed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }

    public function activityLabel(): string
    {
        return 'Consommation '.($this->material?->name ?? '#'.$this->material_id);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VehicleSession::class, 'vehicle_session_id');
    }
}
