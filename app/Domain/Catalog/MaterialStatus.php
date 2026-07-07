<?php

namespace App\Domain\Catalog;

enum MaterialStatus: string
{
    case CONFORME = 'conforme';
    case MANQUANT = 'manquant';
    case HS = 'hs';
    case A_REMPLACER = 'a_remplacer';
    case EN_REPARATION = 'en_reparation';
    case INDISPONIBLE = 'indisponible';

    public function label(): string
    {
        return match ($this) {
            self::CONFORME => 'Conforme',
            self::MANQUANT => 'Manquant',
            self::HS => 'HS',
            self::A_REMPLACER => 'À remplacer',
            self::EN_REPARATION => 'En réparation',
            self::INDISPONIBLE => 'Indisponible',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
