<?php

namespace App\Domain\Protocol;

/**
 * Cible d'un protocole : tout un véhicule, ou un emplacement précis
 * (éventuellement avec ses emplacements enfants).
 */
enum ProtocolScopeType: string
{
    case VEHICLE = 'vehicle';
    case LOCATION = 'location';

    public function label(): string
    {
        return match ($this) {
            self::VEHICLE => 'Véhicule (tout le matériel embarqué)',
            self::LOCATION => 'Emplacement précis',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
