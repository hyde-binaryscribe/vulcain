<?php

namespace App\Domain\Fleet;

/**
 * Moment d'un protocole de service : prise de service (ouverture) ou fin de
 * service (fermeture).
 */
enum ProtocolPhase: string
{
    case OUVERTURE = 'ouverture';
    case FERMETURE = 'fermeture';

    public function label(): string
    {
        return match ($this) {
            self::OUVERTURE => 'Prise de service (ouverture)',
            self::FERMETURE => 'Fin de service (fermeture)',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $p) => ['value' => $p->value, 'label' => $p->label()], self::cases());
    }
}
