<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\ProtocolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Protocol extends Model
{
    /** @use HasFactory<ProtocolFactory> */
    use BelongsToOrganisation, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    protected $fillable = [
        'vehicle_id',
        'protocol_template_id',
        'template_version',
        'user_id',
        'vehicle_name',
        'template_name',
        'types',
        'status',
        'started_at',
        'validated_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'types' => 'array',
            'started_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    /** Libellés lisibles des types figés au démarrage. */
    public function typeLabels(): array
    {
        return \App\Domain\Protocol\ProtocolType::labelsFor($this->types);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProtocolItem::class)->orderBy('display_order');
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
