<?php

namespace App\Models;

use App\Domain\Fleet\DisinfectionType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Protocole de désinfection documenté (procédure). Pré-rempli selon les niveaux
 * recommandés (cadre ARS) pour l'ambulance privée, puis éditable par l'organisation.
 */
class DisinfectionProtocol extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'cadence',
        'frequency_days',
        'procedure',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DisinfectionType::class,
            'frequency_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Véhicules auxquels ce protocole est affecté. */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'disinfection_protocol_vehicle')
            ->withTimestamps();
    }

    /** Protocole daté (avec échéance) ou procédure à l'usage (sans périodicité). */
    public function hasSchedule(): bool
    {
        return $this->frequency_days !== null && $this->frequency_days > 0;
    }

    /**
     * Étapes de la procédure sous forme de liste (une par ligne non vide).
     *
     * @return list<string>
     */
    public function steps(): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", (string) $this->procedure)),
            fn (string $s) => $s !== '',
        ));
    }

    /**
     * Amorce la bibliothèque depuis les recommandations du secteur si l'organisation
     * n'en possède aucune (uniquement pour les secteurs qui en fournissent).
     */
    public static function ensureSeeded(Organisation $organisation): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach ($organisation->profile()->disinfectionProtocols() as $order => $preset) {
            static::create([
                'name' => $preset['name'],
                'type' => $preset['type'],
                'cadence' => $preset['cadence'],
                'frequency_days' => $preset['frequency_days'],
                'procedure' => $preset['procedure'],
                'display_order' => $order,
            ]);
        }
    }
}
