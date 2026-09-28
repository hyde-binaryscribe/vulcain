<?php

namespace App\Domain\Support;

/**
 * Niveau de gravité / urgence unifié (échelle à 3 couleurs) utilisé par les
 * alertes du tableau de bord, la priorité des événements et les notifications.
 *
 *  - CRITICAL (rouge)  : à traiter immédiatement.
 *  - WARNING  (orange) : important, à planifier.
 *  - WATCH    (jaune)  : à surveiller.
 */
enum Severity: string
{
    case CRITICAL = 'critical';
    case WARNING = 'warning';
    case WATCH = 'watch';

    public function label(): string
    {
        return match ($this) {
            self::CRITICAL => 'Critique',
            self::WARNING => 'Important',
            self::WATCH => 'À surveiller',
        };
    }

    /** Rang numérique (3 = plus urgent) pour trier les alertes. */
    public function rank(): int
    {
        return match ($this) {
            self::CRITICAL => 3,
            self::WARNING => 2,
            self::WATCH => 1,
        };
    }

    /** Niveau correspondant à une priorité d'événement (basse/normale/haute). */
    public static function fromEventPriority(string $priority): self
    {
        return match ($priority) {
            'haute' => self::CRITICAL,
            'normale' => self::WARNING,
            default => self::WATCH,
        };
    }
}
