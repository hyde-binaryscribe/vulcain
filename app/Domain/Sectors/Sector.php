<?php

namespace App\Domain\Sectors;

/**
 * Secteur d'activité d'une organisation. Point d'extension de la
 * verticalisation : le cœur métier reste identique, seuls le vocabulaire,
 * le branding et certains presets varient (voir SectorProfile).
 */
enum Sector: string
{
    case SDIS = 'sdis';                       // Sapeurs-pompiers
    case AMBULANCE_PRIVEE = 'ambulance_privee'; // Transport sanitaire privé
    case AASC = 'aasc';                       // Association agréée de sécurité civile

    public static function default(): self
    {
        return self::SDIS;
    }

    public function profile(): SectorProfile
    {
        return SectorProfile::for($this);
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->profile()->label],
            self::cases(),
        );
    }
}
