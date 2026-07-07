<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\InventoryTemplateItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTemplateItem extends Model
{
    /** @use HasFactory<InventoryTemplateItemFactory> */
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'inventory_template_id',
        'material_id',
        'location_id',
        'expected_qty',
        'display_order',
        'photo_required',
    ];

    protected function casts(): array
    {
        return [
            'photo_required' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InventoryTemplate::class, 'inventory_template_id');
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
