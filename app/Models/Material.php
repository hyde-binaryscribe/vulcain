<?php

namespace App\Models;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use BelongsToOrganisation, HasFactory, SoftDeletes;

    public const TRACKING_MODES = [
        'quantity' => 'Quantité',
        'unit' => 'Unité',
    ];

    protected $fillable = [
        'category_id',
        'location_id',
        'reference',
        'name',
        'description',
        'tracking_mode',
        'theoretical_qty',
        'minimum_qty',
        'serial_number',
        'expiry_date',
        'next_check_date',
        'status',
        'observations',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaterialStatus::class,
            'expiry_date' => 'date',
            'next_check_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
