<?php

namespace App\Models;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\MaterialItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Exemplaire physique d'un matériel suivi à l'unité (n° de série).
 */
class MaterialItem extends Model
{
    /** @use HasFactory<MaterialItemFactory> */
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    public function activityLabel(): string
    {
        return 'Exemplaire '.($this->serial_number ?? '#'.$this->getKey());
    }

    protected $fillable = [
        'material_id',
        'location_id',
        'serial_number',
        'status',
        'next_check_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaterialStatus::class,
            'next_check_date' => 'date',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
