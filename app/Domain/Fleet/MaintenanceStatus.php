<?php

namespace App\Domain\Fleet;

use App\Domain\Support\Severity;
use Illuminate\Support\Carbon;

/**
 * Statut d'échéance d'entretien : combine une échéance calendaire (date) et/ou
 * kilométrique (compteur) et en déduit le niveau de gravité (rouge/orange/jaune),
 * partagé avec les alertes.
 */
final class MaintenanceStatus
{
    /** Fenêtre d'alerte orange avant échéance (jours / km). */
    public const SOON_DAYS = 14;

    public const SOON_KM = 500;

    public function __construct(
        public readonly ?Carbon $dueAt,
        public readonly ?int $dueMileage,
        public readonly ?Severity $severity,
        public readonly string $state, // none | ok | soon | overdue
    ) {}

    public static function compute(?Carbon $dueAt, ?int $dueMileage, ?int $currentMileage, ?Carbon $now = null): self
    {
        $now ??= Carbon::now();

        if ($dueAt === null && $dueMileage === null) {
            return new self(null, null, null, 'none');
        }

        $severity = null; // null = ok

        // Axe calendaire.
        if ($dueAt !== null) {
            if ($now->greaterThanOrEqualTo($dueAt)) {
                $severity = Severity::CRITICAL;
            } elseif ($now->greaterThanOrEqualTo($dueAt->copy()->subDays(self::SOON_DAYS))) {
                $severity = Severity::WARNING;
            }
        }

        // Axe kilométrique (on garde le plus grave des deux).
        if ($dueMileage !== null && $currentMileage !== null) {
            if ($currentMileage >= $dueMileage) {
                $severity = Severity::CRITICAL;
            } elseif ($currentMileage >= $dueMileage - self::SOON_KM && $severity !== Severity::CRITICAL) {
                $severity = Severity::WARNING;
            }
        }

        $state = match ($severity) {
            Severity::CRITICAL => 'overdue',
            Severity::WARNING => 'soon',
            default => 'ok',
        };

        return new self($dueAt, $dueMileage, $severity, $state);
    }

    /**
     * Statut agrégé d'un véhicule : pire échéance parmi la dernière opération de
     * chaque type qui porte une échéance suivante.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\MaintenanceRecord>  $records
     */
    public static function forVehicleRecords($records, ?int $currentMileage, ?Carbon $now = null): self
    {
        $withDue = $records->filter(fn ($r) => $r->next_due_at !== null || $r->next_due_mileage !== null);

        $latestPerType = $withDue
            ->groupBy(fn ($r) => $r->type->value)
            ->map(fn ($group) => $group->sortByDesc('performed_at')->first());

        $worst = new self(null, null, null, 'none');
        foreach ($latestPerType as $record) {
            $status = self::compute($record->next_due_at, $record->next_due_mileage, $currentMileage, $now);
            if (($status->severity?->rank() ?? 0) > ($worst->severity?->rank() ?? 0)) {
                $worst = $status;
            }
        }

        return $worst;
    }

    public function label(): string
    {
        return match ($this->state) {
            'overdue' => 'Entretien en retard',
            'soon' => 'Entretien à prévoir',
            'ok' => 'À jour',
            default => 'Aucune échéance',
        };
    }
}
