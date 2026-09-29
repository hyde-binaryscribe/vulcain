<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Point d'anomalie carrosserie positionné sur un schéma du véhicule.
 */
class BodyDamage extends Model
{
    use BelongsToOrganisation, RecordsActivity;

    protected $table = 'vehicle_body_damages';

    protected $fillable = [
        'vehicle_id',
        'view',
        'pos_x',
        'pos_y',
        'description',
        'photo_path',
        'status',
        'reported_by',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'pos_x' => 'float',
            'pos_y' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
