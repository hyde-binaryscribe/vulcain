<?php

namespace App\Models;

use App\Domain\Storage\LocationKind;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Emplacement d'un gabarit de modèle de véhicule (mobile ou sac), avec
 * hiérarchie possible via parent_id (sous-emplacement).
 */
class VehicleModelLocation extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'vehicle_model_id',
        'parent_id',
        'name',
        'kind',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => LocationKind::class,
            'display_order' => 'integer',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order');
    }
}
