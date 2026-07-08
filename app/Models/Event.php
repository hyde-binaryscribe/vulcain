<?php

namespace App\Models;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use BelongsToOrganisation, HasFactory, RecordsActivity;

    public const PRIORITIES = ['basse', 'normale', 'haute'];

    protected $fillable = [
        'type',
        'title',
        'description',
        'status',
        'priority',
        'vehicle_id',
        'material_id',
        'protocol_id',
        'protocol_item_id',
        'created_by',
        'assigned_to',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'status' => EventStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(EventComment::class)->orderBy('created_at');
    }
}
