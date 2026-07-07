<?php

namespace App\Models;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    public const MODE_QUANTITY = 'quantity';

    public const MODE_SERIAL = 'serial';

    public const MODE_LOT = 'lot';

    public const TRACKING_MODES = [
        self::MODE_QUANTITY => 'Quantité',
        self::MODE_SERIAL => 'Unitaire (n° de série)',
        self::MODE_LOT => 'Consommable (lot / péremption)',
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
        'current_qty',
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

    /** Exemplaires physiques (mode unitaire). */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialItem::class);
    }

    /** Lots périssables (mode consommable). */
    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    /** Quantité réellement en stock, selon le mode de suivi. */
    public function stockQuantity(): int
    {
        return match ($this->tracking_mode) {
            self::MODE_SERIAL => $this->items()->count(),
            self::MODE_LOT => (int) $this->lots()->sum('quantity'),
            default => (int) $this->current_qty,
        };
    }

    public function isBelowThreshold(): bool
    {
        return $this->minimum_qty > 0 && $this->stockQuantity() < $this->minimum_qty;
    }
}
