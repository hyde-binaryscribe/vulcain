<?php

namespace App\Domain\Events;

/** Colonnes du Kanban de gestion des événements. */
enum EventStatus: string
{
    case A_TRAITER = 'a_traiter';
    case EN_COURS = 'en_cours';
    case RESOLU = 'resolu';
    case FERME = 'ferme';

    public function label(): string
    {
        return match ($this) {
            self::A_TRAITER => 'À traiter',
            self::EN_COURS => 'En cours',
            self::RESOLU => 'Résolu',
            self::FERME => 'Fermé',
        };
    }

    /** Ordre des colonnes (gauche → droite). */
    public static function ordered(): array
    {
        return [self::A_TRAITER, self::EN_COURS, self::RESOLU, self::FERME];
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }

    /** Un événement dans cet état est-il « clos » (résolu/fermé) ? */
    public function isClosed(): bool
    {
        return $this === self::RESOLU || $this === self::FERME;
    }
}
