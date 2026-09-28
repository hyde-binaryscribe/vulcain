<?php

namespace App\Models;

use App\Domain\Fleet\DisinfectionType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opération de désinfection / nettoyage d'un véhicule (traçabilité).
 */
class DisinfectionRecord extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity;

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'type',
        'performed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => DisinfectionType::class,
            'performed_at' => 'datetime',
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
