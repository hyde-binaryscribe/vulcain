<?php

namespace App\Domain\Billing;

enum SubscriptionStatus: string
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TRIAL => 'Essai',
            self::ACTIVE => 'Actif',
            self::PAST_DUE => 'Impayé',
            self::SUSPENDED => 'Suspendu',
            self::CANCELLED => 'Résilié',
        };
    }

    /** L'abonnement donne-t-il accès au service ? */
    public function grantsAccess(): bool
    {
        return $this === self::TRIAL || $this === self::ACTIVE;
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
