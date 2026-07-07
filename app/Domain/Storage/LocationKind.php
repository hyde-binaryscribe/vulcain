<?php

namespace App\Domain\Storage;

/**
 * Nature d'un emplacement : mobile (à bord d'un véhicule) ou fixe
 * (dépôt, pièce de stock, réserve). Le suivi des péremptions peut être
 * modulé différemment selon cette nature (cf. réglage de l'organisation).
 */
enum LocationKind: string
{
    case MOBILE = 'mobile';
    case FIXE = 'fixe';

    public function label(): string
    {
        return match ($this) {
            self::MOBILE => 'Mobile (véhicule)',
            self::FIXE => 'Fixe (dépôt / stock)',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label()], self::cases());
    }
}
