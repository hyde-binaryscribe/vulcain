<?php

namespace App\Models;

use App\Domain\Storage\LocationKind;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'kind',
        'parent_id',
        'holder_material_id',
        'name',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => LocationKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order');
    }

    /** Matériel qui porte physiquement cet emplacement (ex. pochette d'un Lifepak). */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'holder_material_id');
    }

    public function isMobile(): bool
    {
        return $this->kind === LocationKind::MOBILE;
    }

    /**
     * Chemin lisible « Racine › Parent › Emplacement ».
     *
     * La racine est le nom du véhicule (mobile) ou le nom de l'emplacement
     * racine (fixe : dépôt, pièce de stock). Remonte la chaîne des parents.
     */
    public function fullPath(string $separator = ' › '): string
    {
        $segments = [];
        $node = $this;
        $guard = 0;

        // Remonte la hiérarchie des emplacements (garde-fou anti-boucle).
        while ($node !== null && $guard++ < 20) {
            array_unshift($segments, $node->name);
            $node = $node->relationLoaded('parent') ? $node->parent : $node->parent()->first();
        }

        // Préfixe véhicule pour les emplacements mobiles.
        $vehicleName = $this->relationLoaded('vehicle') ? $this->vehicle?->name : $this->vehicle()->value('name');
        if ($vehicleName !== null) {
            array_unshift($segments, $vehicleName);
        }

        return implode($separator, $segments);
    }
}
