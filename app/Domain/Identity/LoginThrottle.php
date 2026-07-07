<?php

namespace App\Domain\Identity;

use App\Models\LoginAttempt;
use Illuminate\Support\Carbon;

/**
 * Blocage temporaire des connexions après trop d'échecs, basé sur la table
 * login_attempts (source de vérité + traçabilité). Le comptage se fait par
 * couple (organisation, e-mail) sur une fenêtre glissante.
 */
class LoginThrottle
{
    public function __construct(
        private readonly int $maxAttempts,
        private readonly int $decayMinutes,
    ) {}

    public function tooManyAttempts(?int $organisationId, string $email): bool
    {
        return $this->recentFailures($organisationId, $email) >= $this->maxAttempts;
    }

    public function recentFailures(?int $organisationId, string $email): int
    {
        return $this->baseQuery($organisationId, $email)->count();
    }

    /** Secondes restantes avant de pouvoir réessayer. */
    public function availableIn(?int $organisationId, string $email): int
    {
        $oldest = $this->baseQuery($organisationId, $email)->min('created_at');

        if ($oldest === null) {
            return 0;
        }

        $unlockAt = Carbon::parse($oldest)->addMinutes($this->decayMinutes);

        return max(0, now()->diffInSeconds($unlockAt, false));
    }

    public function record(?int $organisationId, string $email, ?string $ip, ?string $userAgent, bool $successful): void
    {
        LoginAttempt::create([
            'organisation_id' => $organisationId,
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'successful' => $successful,
            'created_at' => now(),
        ]);
    }

    /** Réinitialise le compteur d'échecs récents (après une connexion réussie). */
    public function clear(?int $organisationId, string $email): void
    {
        $this->baseQuery($organisationId, $email)->delete();
    }

    private function baseQuery(?int $organisationId, string $email)
    {
        return LoginAttempt::query()
            ->where('successful', false)
            ->where('email', $email)
            ->where('organisation_id', $organisationId)
            ->where('created_at', '>=', now()->subMinutes($this->decayMinutes));
    }
}
