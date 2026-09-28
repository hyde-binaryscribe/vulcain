<?php

namespace App\Domain\Fleet;

/**
 * Nature d'une opération de désinfection / nettoyage d'un véhicule.
 */
enum DisinfectionType: string
{
    case COURANT = 'nettoyage_courant';
    case DESINFECTION = 'desinfection';
    case APPROFONDI = 'bio_nettoyage';

    public function label(): string
    {
        return match ($this) {
            self::COURANT => 'Nettoyage courant',
            self::DESINFECTION => 'Désinfection',
            self::APPROFONDI => 'Bio-nettoyage approfondi',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $t) => ['value' => $t->value, 'label' => $t->label()],
            self::cases(),
        );
    }
}
