<?php

namespace App\Models;

use App\Domain\Inventory\InventoryItemState;
use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'inventory_id',
        'material_id',
        'material_name',
        'reference',
        'location_name',
        'tracking_mode',
        'expected_qty',
        'photo_required',
        'display_order',
        'observed_qty',
        'state',
        'observation',
        'checked',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'state' => InventoryItemState::class,
            'photo_required' => 'boolean',
            'checked' => 'boolean',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
}
