<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\ProtocolTemplateItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolTemplateItem extends Model
{
    /** @use HasFactory<ProtocolTemplateItemFactory> */
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'protocol_template_id',
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
        return $this->belongsTo(ProtocolTemplate::class, 'protocol_template_id');
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
