<?php

namespace App\Models;

use App\Domain\Catalog\MaterialStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\StockLotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lot périssable d'un consommable (n° de lot, quantité, date de péremption).
 * Base de la gestion FEFO.
 */
class StockLot extends Model
{
    /** @use HasFactory<StockLotFactory> */
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    public function activityLabel(): string
    {
        return 'Lot '.($this->lot_number ?? '#'.$this->getKey());
    }

    protected $fillable = [
        'material_id',
        'location_id',
        'lot_number',
        'quantity',
        'received_at',
        'expiry_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaterialStatus::class,
            'received_at' => 'date',
            'expiry_date' => 'date',
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

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function expiresWithin(int $days): bool
    {
        return $this->expiry_date !== null
            && ! $this->isExpired()
            && $this->expiry_date->lte(now()->addDays($days));
    }
}
