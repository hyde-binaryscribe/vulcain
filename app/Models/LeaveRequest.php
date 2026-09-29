<?php

namespace App\Models;

use App\Domain\Hr\LeaveStatus;
use App\Domain\Hr\LeaveType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de congé / absence d'un utilisateur, soumise à validation.
 */
class LeaveRequest extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity;

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'status',
        'reviewer_id',
        'decided_at',
        'decision_note',
        'modified_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'decided_at' => 'datetime',
            'modified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** Nombre de jours calendaires (bornes incluses). */
    public function days(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
