<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use BelongsToOrganisation, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    protected $fillable = [
        'vehicle_id',
        'inventory_template_id',
        'template_version',
        'user_id',
        'vehicle_name',
        'template_name',
        'status',
        'started_at',
        'validated_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
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
        return $this->hasMany(InventoryItem::class)->orderBy('display_order');
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
