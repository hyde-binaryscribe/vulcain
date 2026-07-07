<?php

namespace App\Models;

use App\Domain\Protocol\ProtocolFrequency;
use App\Domain\Protocol\ProtocolType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\ProtocolTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProtocolTemplate extends Model
{
    /** @use HasFactory<ProtocolTemplateFactory> */
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'name',
        'types',
        'frequency',
        'custom_days',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'types' => 'array',
            'frequency' => ProtocolFrequency::class,
            'is_active' => 'boolean',
        ];
    }

    /** Libellés lisibles des types portés par ce modèle. */
    public function typeLabels(): array
    {
        return ProtocolType::labelsFor($this->types);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProtocolTemplateItem::class)->orderBy('display_order');
    }

    /** Nombre de jours du cycle (fréquence standard ou personnalisée). */
    public function cycleDays(): ?int
    {
        return $this->frequency === ProtocolFrequency::CUSTOM
            ? $this->custom_days
            : $this->frequency->days();
    }
}
