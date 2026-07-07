<?php

namespace App\Domain\Protocol;

enum ProtocolFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Quotidienne',
            self::WEEKLY => 'Hebdomadaire',
            self::MONTHLY => 'Mensuelle',
            self::QUARTERLY => 'Trimestrielle',
            self::CUSTOM => 'Personnalisée',
        };
    }

    /** Nombre de jours entre deux contrôles (null pour personnalisée sans valeur). */
    public function days(): ?int
    {
        return match ($this) {
            self::DAILY => 1,
            self::WEEKLY => 7,
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::CUSTOM => null,
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $f) => ['value' => $f->value, 'label' => $f->label()], self::cases());
    }
}
