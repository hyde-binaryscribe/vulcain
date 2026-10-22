<?php

namespace App\Models;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Domain\Fleet\VehicleStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        'vin',
        'telematics_imei',
        'photo_path',
        'status',
        'commissioned_at',
        'mileage',
        'mileage_at',
        'mileage_source',
        'mileage_by',
        'observations',
    ];

    /**
     * Champs partagés avec Itineris : leur modification incrémente `version`
     * (concurrence optimiste du lien, voir docs/LIEN-ITINERIS.md). Le
     * kilométrage, mis à jour en continu par la télématique, n'en fait pas partie.
     */
    public const SHARED_FIELDS = [
        'name', 'type', 'callsign', 'registration', 'vin', 'telematics_imei',
        'commissioned_at', 'vehicle_model_id', 'status', 'deleted_at',
    ];

    /**
     * Saut maximal accepté d'un relevé à l'autre (décision du porteur) : au-delà, le relevé est
     * mis en alerte « à vérifier » et le compteur n'est pas modifié.
     */
    public const MILEAGE_JUMP_ALERT_KM = 2000;

    /** Libellés des sources de relevé kilométrique. */
    public const MILEAGE_SOURCES = [
        'prise_de_service' => 'prise de service',
        'fin_de_service' => 'fin de service',
        'plein' => 'plein',
        'entretien' => 'entretien',
        'fiche' => 'fiche véhicule',
    ];

    /** Vrai pendant recordMileage() : la source posée n'est pas remplacée par « fiche ». */
    private bool $recordingMileage = false;

    /**
     * Relevé kilométrique venant du terrain (prise ou fin de service, plein, entretien).
     * Le compteur ne recule jamais (décision du porteur) : un relevé plus bas est ignoré
     * (il reste dans sa saisie). Un saut de plus de MILEAGE_JUMP_ALERT_KM crée un
     * événement « relevé à vérifier » au lieu d'être appliqué.
     *
     * @return bool vrai si le compteur a été mis à jour
     */
    public function recordMileage(?int $km, string $source, ?string $by = null): bool
    {
        if ($km === null || $km <= 0) {
            return false;
        }
        $current = $this->mileage !== null ? (int) $this->mileage : null;
        if ($current !== null && $km <= $current) {
            return false;
        }
        if ($current !== null && $current > 0 && $km - $current > self::MILEAGE_JUMP_ALERT_KM) {
            $this->flagMileageJump($km, $current, $source, $by);

            return false;
        }

        $this->recordingMileage = true;
        try {
            $this->update(['mileage' => $km, 'mileage_at' => now(), 'mileage_source' => $source, 'mileage_by' => $by]);
        } finally {
            $this->recordingMileage = false;
        }

        return true;
    }

    /** Événement « relevé à vérifier » (un seul ouvert par véhicule, mis à jour au relevé suivant). */
    private function flagMileageJump(int $km, int $current, string $source, ?string $by): void
    {
        $label = self::MILEAGE_SOURCES[$source] ?? $source;
        $description = sprintf(
            'Relevé de %s km (%s%s, %s) : +%s km depuis le compteur actuel (%s km). Compteur non mis à jour : corrigez-le dans la fiche du véhicule si le relevé est juste.',
            number_format($km, 0, ',', ' '), $label, $by ? ', '.$by : '', now()->format('d/m/Y H:i'),
            number_format($km - $current, 0, ',', ' '), number_format($current, 0, ',', ' '),
        );
        $sourceKey = 'mileage-jump:'.$this->id;
        $open = Event::query()->where('source_key', $sourceKey)
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->first();
        if ($open !== null) {
            $open->update(['description' => $description]);

            return;
        }
        Event::create([
            'type' => EventType::ANOMALIE->value,
            'source_key' => $sourceKey,
            'title' => "Relevé kilométrique à vérifier — {$this->name}",
            'description' => $description,
            'priority' => 'normale',
            'status' => EventStatus::A_TRAITER->value,
            'kanban_column_id' => KanbanBoard::entryColumnId($this->organisation),
            'vehicle_id' => $this->id,
            'created_by' => null,
        ]);
    }

    protected static function booted(): void
    {
        // Kilométrage corrigé à la main (fiche, administration) : relevé daté, source « fiche ».
        static::saving(function (Vehicle $vehicle) {
            if ($vehicle->isDirty('mileage') && ! $vehicle->recordingMileage && $vehicle->mileage !== null) {
                $vehicle->mileage_at = now();
                $vehicle->mileage_source = 'fiche';
                $vehicle->mileage_by = Auth::user()?->name;
            }
        });

        static::creating(function (Vehicle $vehicle) {
            $vehicle->uuid ??= (string) Str::uuid();
            $vehicle->version ??= 1;
        });

        // Incrément hors Eloquent : pas d'entrée parasite dans le journal d'activité.
        static::updated(function (Vehicle $vehicle) {
            if ($vehicle->wasChanged(self::SHARED_FIELDS)) {
                $row = DB::table('vehicles')->where('id', $vehicle->id);
                $row->increment('version');
                $vehicle->setRawAttributes(
                    array_merge($vehicle->getAttributes(), ['version' => (int) $row->value('version')]),
                    true,
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'commissioned_at' => 'date',
            'mileage_at' => 'datetime',
            'version' => 'integer',
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
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->orderBy('category')->orderBy('title');
    }

    /** Points d'anomalie carrosserie (ouverts d'abord, plus récents ensuite). */
    public function bodyDamages(): HasMany
    {
        return $this->hasMany(BodyDamage::class)->orderByRaw("status = 'ouverte' desc")->latest('id');
    }
}
