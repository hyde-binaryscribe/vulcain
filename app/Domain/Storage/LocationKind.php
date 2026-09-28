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
    case SAC = 'sac';

    public function label(): string
    {
        return match ($this) {
            self::MOBILE => 'Mobile (véhicule)',
            self::FIXE => 'Fixe (dépôt / stock)',
            self::SAC => 'Sac',
        };
    }

    /** Natures rattachées à un véhicule (exigent un véhicule). */
    public function requiresVehicle(): bool
    {
        return $this === self::MOBILE || $this === self::SAC;
    }

    /**
     * @param  bool  $withBags  inclure la nature « Sac » (activée par l'organisation)
     * @return list<array{value:string,label:string}>
     */
    public static function options(bool $withBags = true): array
    {
        return array_values(array_map(
            fn (self $k) => ['value' => $k->value, 'label' => $k->label()],
            array_filter(self::cases(), fn (self $k) => $withBags || $k !== self::SAC),
        ));
    }
}
