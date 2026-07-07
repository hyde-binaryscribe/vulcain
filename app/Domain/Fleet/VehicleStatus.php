<?php

namespace App\Domain\Fleet;

enum VehicleStatus: string
{
    case DISPONIBLE = 'disponible';
    case INDISPONIBLE = 'indisponible';
    case MAINTENANCE = 'maintenance';
    case REPARATION = 'reparation';
    case REFORME = 'reforme';

    public function label(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'Disponible',
            self::INDISPONIBLE => 'Indisponible',
            self::MAINTENANCE => 'En maintenance',
            self::REPARATION => 'En réparation',
            self::REFORME => 'Réformé',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
