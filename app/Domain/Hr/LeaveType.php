<?php

namespace App\Domain\Hr;

/**
 * Nature d'une demande de congé / absence.
 */
enum LeaveType: string
{
    case CONGE_PAYE = 'conge_paye';
    case RTT = 'rtt';
    case SANS_SOLDE = 'sans_solde';
    case MALADIE = 'maladie';
    case ABSENCE = 'absence';
    case FORMATION = 'formation';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::CONGE_PAYE => 'Congé payé',
            self::RTT => 'RTT',
            self::SANS_SOLDE => 'Congé sans solde',
            self::MALADIE => 'Arrêt maladie',
            self::ABSENCE => 'Absence',
            self::FORMATION => 'Formation',
            self::AUTRE => 'Autre',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
