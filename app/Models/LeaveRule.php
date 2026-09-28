<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Règle de congés par rôle métier : effectif simultané maximum en absence et
 * droits annuels (jours) accordés.
 */
class LeaveRule extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity;

    protected $fillable = [
        'job_role',
        'max_simultaneous',
        'annual_days',
    ];

    protected function casts(): array
    {
        return [
            'max_simultaneous' => 'integer',
            'annual_days' => 'integer',
        ];
    }
}
