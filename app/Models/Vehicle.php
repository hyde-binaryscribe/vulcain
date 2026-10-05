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
        'vehicle_model_id',
        'vehicle_motorization_id',
        'callsign',
        'registration',
        'telematics_imei',
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

    /** Positions télématiques (géoloc + OBD), les plus récentes d'abord. */
    public function positions(): HasMany
    {
        return $this->hasMany(VehiclePosition::class)->latest('device_time');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
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

    /** Journal des désinfections / nettoyages (plus récent d'abord). */
    public function disinfections(): HasMany
    {
        return $this->hasMany(DisinfectionRecord::class)->latest('performed_at');
    }

    /** Protocoles de désinfection affectés (portent la périodicité). */
    public function disinfectionProtocols(): BelongsToMany
    {
        return $this->belongsToMany(DisinfectionProtocol::class, 'disinfection_protocol_vehicle')
            ->withTimestamps();
    }

    /** Journal du suivi mécanique (plus récent d'abord). */
    public function maintenances(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class)->latest('performed_at');
    }

    /** Pleins de carburant (plus récent d'abord). */
    public function fuelRecords(): HasMany
    {
        return $this->hasMany(FuelRecord::class)->latest('mileage');
    }

    /** Tâches persistantes du véhicule (à faire d'abord, puis les plus récentes). */
    public function tasks(): HasMany
    {
        return $this->hasMany(VehicleTask::class)->orderByRaw('done_at is null desc')->latest('id');
    }

    /** Documents du véhicule (agrément, CT, carte grise…). */
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->orderBy('category')->orderBy('title');
    }

    /** Points d'anomalie carrosserie (ouverts d'abord, plus récents ensuite). */
    public function bodyDamages(): HasMany
    {
        return $this->hasMany(BodyDamage::class)->orderByRaw("status = 'ouverte' desc")->latest('id');
    }
}
