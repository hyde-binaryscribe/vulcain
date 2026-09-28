<?php

namespace App\Domain\Hr;

/**
 * Statut d'une demande de congé / absence dans le workflow de validation.
 */
enum LeaveStatus: string
{
    case PENDING = 'en_attente';
    case APPROVED = 'approuve';
    case REFUSED = 'refuse';
    case CANCELLED = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::APPROVED => 'Approuvé',
            self::REFUSED => 'Refusé',
            self::CANCELLED => 'Annulé',
        };
    }
}
