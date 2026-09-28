<?php

namespace App\Models;

use App\Domain\Fleet\DisinfectionType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opération de désinfection / nettoyage d'un véhicule (traçabilité).
 */
class DisinfectionRecord extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity;

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'type',
        'disinfection_protocol_id',
        'steps',
        'performed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => DisinfectionType::class,
            'steps' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    /** Étapes réalisées / total (instantané figé). @return array{done:int,total:int} */
    public function stepProgress(): array
    {
        $steps = is_array($this->steps) ? $this->steps : [];

        return [
            'done' => count(array_filter($steps, fn ($s) => ! empty($s['done']))),
            'total' => count($steps),
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(DisinfectionProtocol::class, 'disinfection_protocol_id');
    }
}
