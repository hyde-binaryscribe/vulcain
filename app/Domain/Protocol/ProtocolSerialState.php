<?php

namespace App\Domain\Protocol;

/**
 * État d'un exemplaire série lors d'un contrôle : on sait quel appareil
 * (n° de série) est censé être à cet endroit, on vérifie sa présence.
 */
enum ProtocolSerialState: string
{
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case PRESENT_ANOMALIE = 'present_anomalie';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Présent',
            self::ABSENT => 'Absent',
            self::PRESENT_ANOMALIE => 'Présent avec anomalie',
        };
    }

    public function isAnomaly(): bool
    {
        return $this !== self::PRESENT;
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
