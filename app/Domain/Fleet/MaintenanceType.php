<?php

namespace App\Domain\Fleet;

/**
 * Nature d'une opération de suivi mécanique d'un véhicule.
 */
enum MaintenanceType: string
{
    case REVISION = 'revision';
    case VIDANGE = 'vidange';
    case CONTROLE_TECHNIQUE = 'controle_technique';
    case PNEUS = 'pneus';
    case REPARATION = 'reparation';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::REVISION => 'Révision / entretien',
            self::VIDANGE => 'Vidange',
            self::CONTROLE_TECHNIQUE => 'Contrôle technique',
            self::PNEUS => 'Pneumatiques',
            self::REPARATION => 'Réparation',
            self::AUTRE => 'Autre',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
