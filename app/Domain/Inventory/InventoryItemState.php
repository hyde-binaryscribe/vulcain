<?php

namespace App\Domain\Inventory;

enum InventoryItemState: string
{
    case CONFORME = 'conforme';
    case MANQUANT = 'manquant';
    case HS = 'hs';
    case A_REMPLACER = 'a_remplacer';

    public function label(): string
    {
        return match ($this) {
            self::CONFORME => 'Conforme',
            self::MANQUANT => 'Manquant',
            self::HS => 'HS',
            self::A_REMPLACER => 'À remplacer',
        };
    }

    public function isAnomaly(): bool
    {
        return $this !== self::CONFORME;
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
