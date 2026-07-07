<?php

namespace App\Models;

use App\Domain\Protocol\ProtocolItemState;
use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\ProtocolItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolItem extends Model
{
    /** @use HasFactory<ProtocolItemFactory> */
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'protocol_id',
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
            'state' => ProtocolItemState::class,
            'photo_required' => 'boolean',
            'checked' => 'boolean',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }
}
