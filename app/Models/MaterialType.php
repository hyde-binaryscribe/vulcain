<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Type de matériel (Thermomètre, Scope, Compresse 5×5…). Porte le mode de suivi
 * commun à tous ses modèles : durable (n° de série), consommable (lot), ou
 * simple quantité.
 */
class MaterialType extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'tracking_mode',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    /** Modèles rattachés à ce type (marque + modèle). */
    public function models(): HasMany
    {
        return $this->hasMany(Material::class, 'material_type_id');
    }
}
