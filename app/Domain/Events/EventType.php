<?php

namespace App\Domain\Events;

enum EventType: string
{
    case ANOMALIE = 'anomalie';
    case REPARATION = 'reparation';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::ANOMALIE => 'Anomalie',
            self::REPARATION => 'Réparation',
            self::AUTRE => 'Autre',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
