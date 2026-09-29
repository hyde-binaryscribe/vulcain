<?php

namespace App\Domain\Fleet;

use App\Domain\Support\Severity;
use Illuminate\Support\Carbon;

/**
 * Statut de désinfection d'un véhicule, déduit de sa dernière désinfection et de
 * la périodicité imposée par son type. Fournit l'échéance et le niveau de gravité
 * (rouge/orange/jaune) partagé avec les alertes.
 */
final class DisinfectionStatus
{
    /** Fenêtre (jours) avant échéance à partir de laquelle on alerte en orange. */
    public const SOON_DAYS = 3;

    public function __construct(
        public readonly ?int $intervalDays,
        public readonly ?Carbon $lastAt,
        public readonly ?Carbon $dueAt,
        public readonly ?Severity $severity,
        public readonly string $state, // none | ok | soon | overdue | never
    ) {}

    /**
     * Statut agrégé d'un véhicule sur l'ensemble de ses protocoles affectés :
     * on conserve le plus urgent (en retard > jamais fait > à prévoir > à jour).
     *
     * @param  iterable<array{interval:?int,last:?Carbon}>  $entries
     */
    public static function forProtocols(iterable $entries, ?Carbon $now = null): self
    {
        $now ??= Carbon::now();
        $worst = new self(null, null, null, null, 'none');

        foreach ($entries as $entry) {
            $status = self::compute($entry['last'] ?? null, $entry['interval'] ?? null, $now);

            if ($status->state === 'none') {
                continue;
            }

            if (self::order($status) > self::order($worst)) {
                $worst = $status;
            }
        }

        return $worst;
    }

    /** Ordre d'urgence pour l'agrégation multi-protocoles. */
    private static function order(self $status): int
    {
        return match ($status->state) {
            'overdue' => 4,
            'never' => 3,
            'soon' => 2,
            'ok' => 1,
            default => 0, // none
        };
    }

    public static function compute(?Carbon $lastAt, ?int $intervalDays, ?Carbon $now = null): self
    {
        $now ??= Carbon::now();

        // Aucune périodicité imposée : pas d'échéance ni d'alerte.
        if ($intervalDays === null || $intervalDays <= 0) {
            return new self(null, $lastAt, null, null, 'none');
        }

        // Périodicité imposée mais jamais désinfecté : à faire.
        if ($lastAt === null) {
            return new self($intervalDays, null, null, Severity::WARNING, 'never');
        }

        $dueAt = $lastAt->copy()->addDays($intervalDays);

        if ($now->greaterThanOrEqualTo($dueAt)) {
            return new self($intervalDays, $lastAt, $dueAt, Severity::CRITICAL, 'overdue');
        }

        if ($now->greaterThanOrEqualTo($dueAt->copy()->subDays(self::SOON_DAYS))) {
            return new self($intervalDays, $lastAt, $dueAt, Severity::WARNING, 'soon');
        }

        return new self($intervalDays, $lastAt, $dueAt, null, 'ok');
    }

    public function label(): string
    {
        return match ($this->state) {
            'overdue' => 'Désinfection en retard',
            'soon' => 'Désinfection à prévoir',
            'never' => 'Jamais désinfecté',
            'ok' => 'À jour',
            default => 'Sans périodicité',
        };
    }
}
