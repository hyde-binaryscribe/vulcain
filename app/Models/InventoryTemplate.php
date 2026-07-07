<?php

namespace App\Models;

use App\Domain\Inventory\InventoryFrequency;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\InventoryTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryTemplate extends Model
{
    /** @use HasFactory<InventoryTemplateFactory> */
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'name',
        'frequency',
        'custom_days',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => InventoryFrequency::class,
            'is_active' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTemplateItem::class)->orderBy('display_order');
    }

    /** Nombre de jours du cycle (fréquence standard ou personnalisée). */
    public function cycleDays(): ?int
    {
        return $this->frequency === InventoryFrequency::CUSTOM
            ? $this->custom_days
            : $this->frequency->days();
    }
}
