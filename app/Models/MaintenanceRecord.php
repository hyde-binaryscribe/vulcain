<?php

namespace App\Models;

use App\Domain\Fleet\MaintenanceType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opération de suivi mécanique d'un véhicule (entretien, réparation, contrôle…).
 */
class MaintenanceRecord extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity;

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'type',
        'performed_at',
        'mileage',
        'cost',
        'provider',
        'notes',
        'next_due_at',
        'next_due_mileage',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'performed_at' => 'date',
            'next_due_at' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
