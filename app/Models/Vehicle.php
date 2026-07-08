<?php

namespace App\Models;

use App\Domain\Fleet\VehicleStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'site_id',
        'name',
        'type',
        'callsign',
        'registration',
        'center',
        'photo_path',
        'status',
        'commissioned_at',
        'mileage',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'commissioned_at' => 'date',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Utilisateurs autorisés sur ce véhicule. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vehicle_user');
    }

    /** Emplacements rattachés au véhicule. */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
