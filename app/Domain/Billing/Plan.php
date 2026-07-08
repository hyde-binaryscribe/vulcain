<?php

namespace App\Domain\Billing;

/**
 * Plans d'abonnement du SaaS. La facturation (Stripe) est différée : ici on
 * définit les libellés, les limites et un prix indicatif. Extensible.
 */
enum Plan: string
{
    case DECOUVERTE = 'decouverte';
    case STANDARD = 'standard';
    case PRO = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::DECOUVERTE => 'Découverte',
            self::STANDARD => 'Standard',
            self::PRO => 'Pro',
        };
    }

    /** Limite d'utilisateurs (null = illimité). */
    public function maxUsers(): ?int
    {
        return match ($this) {
            self::DECOUVERTE => 3,
            self::STANDARD => 20,
            self::PRO => null,
        };
    }

    /** Limite de véhicules (null = illimité). */
    public function maxVehicles(): ?int
    {
        return match ($this) {
            self::DECOUVERTE => 2,
            self::STANDARD => 20,
            self::PRO => null,
        };
    }

    /** Limite de sites (null = illimité). */
    public function maxSites(): ?int
    {
        return match ($this) {
            self::DECOUVERTE => 1,
            self::STANDARD => 5,
            self::PRO => null,
        };
    }

    /** Prix mensuel indicatif (euros HT). */
    public function monthlyPrice(): int
    {
        return match ($this) {
            self::DECOUVERTE => 0,
            self::STANDARD => 49,
            self::PRO => 149,
        };
    }

    /** @return array<string, int|null> */
    public function limits(): array
    {
        return [
            'users' => $this->maxUsers(),
            'vehicles' => $this->maxVehicles(),
            'sites' => $this->maxSites(),
        ];
    }

    /** @return list<array{value:string,label:string,price:int,limits:array<string,int|null>}> */
    public static function options(): array
    {
        return array_map(fn (self $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'price' => $p->monthlyPrice(),
            'limits' => $p->limits(),
        ], self::cases());
    }
}
