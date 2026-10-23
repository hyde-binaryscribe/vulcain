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
use Illuminate\Support\Str;

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
        'material_type_id',
        'brand',
        'location_id',
        'reference',
        'name',
        'identifier',
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

    protected static function booted(): void
    {
        static::creating(function (Material $m) {
            if (empty($m->identifier)) {
                $m->identifier = static::buildIdentifier($m);
            }
        });
    }

    /**
     * Identifiant auto « CODE_VEHICULE_NN » : code du type de matériel (ou dérivé
     * du nom), code du véhicule (indicatif), puis numéro d'ordre par type et par
     * véhicule. Renvoie null si indéterminable.
     */
    public static function buildIdentifier(Material $m): ?string
    {
        try {
            $code = $m->material_type_id
                ? strtoupper((string) MaterialType::query()->whereKey($m->material_type_id)->value('code'))
                : '';
            if ($code === '') {
                $code = MaterialType::generateCode((string) $m->name);
            }

            $vehicle = $m->location_id
                ? Location::query()->whereKey($m->location_id)->with('vehicle:id,callsign,name')->first()?->vehicle
                : null;
            $veh = 'X';
            if ($vehicle !== null) {
                $veh = strtoupper(preg_replace('/[^A-Z0-9]/', '', Str::ascii($vehicle->callsign ?: ($vehicle->name ?? '')))) ?: 'X';
            }

            $seq = 1;
            if ($vehicle !== null) {
                $locationIds = Location::query()->where('vehicle_id', $vehicle->id)->pluck('id');
                $query = static::query()->whereIn('location_id', $locationIds);
                $m->material_type_id
                    ? $query->where('material_type_id', $m->material_type_id)
                    : $query->where('name', $m->name);
                $seq = $query->count() + 1;
            }

            return $code.'_'.$veh.'_'.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
        } catch (\Throwable) {
            return null;
        }
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    /** Type de matériel (Thermomètre, Compresse 5×5…) — porte le mode de suivi. */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class, 'material_type_id');
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
