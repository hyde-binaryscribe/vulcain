<?php

namespace App\Domain\Fleet;

/**
 * Niveau d'obligation d'un équipement dans l'armement de référence d'un type
 * de véhicule : obligatoire, conditionnel (selon service / département) ou
 * facultatif.
 */
enum ArmamentLevel: string
{
    case OBLIGATOIRE = 'obligatoire';
    case CONDITIONNEL = 'conditionnel';
    case FACULTATIF = 'facultatif';

    public function label(): string
    {
        return match ($this) {
            self::OBLIGATOIRE => 'Obligatoire',
            self::CONDITIONNEL => 'Conditionnel (selon service)',
            self::FACULTATIF => 'Facultatif',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $l) => ['value' => $l->value, 'label' => $l->label()], self::cases());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $l) => $l->value, self::cases());
    }
}
