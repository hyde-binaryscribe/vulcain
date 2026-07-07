<?php

namespace App\Domain\Identity;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Réinitialisation de mot de passe cloisonnée par organisation.
 *
 * Jeton : cryptographiquement sûr, temporaire, à usage unique, stocké HACHÉ
 * (SHA-256). Comportement volontairement uniforme (pas d'énumération de comptes).
 */
class PasswordResetService
{
    public function __construct(
        private readonly int $expiresMinutes,
        private readonly int $throttleSeconds,
    ) {}

    public function sendResetLink(int $organisationId, string $email): void
    {
        // Recherche cloisonnée (scope global actif dans la requête tenant).
        $user = User::query()->where('email', $email)->first();

        // Ne rien révéler : compte inexistant ou désactivé -> aucune action visible.
        if ($user === null || ! $user->isActive()) {
            return;
        }

        $existing = DB::table('password_reset_tokens')
            ->where('organisation_id', $organisationId)
            ->where('email', $email)
            ->first();

        // Anti-spam : ne pas régénérer un jeton trop récent.
        if ($existing !== null && Carbon::parse($existing->created_at)->gt(now()->subSeconds($this->throttleSeconds))) {
            return;
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['organisation_id' => $organisationId, 'email' => $email],
            ['token' => hash('sha256', $token), 'created_at' => now()],
        );

        // URL construite sur l'hôte courant (sous-domaine de l'organisation).
        $url = URL::to('/reset-password/'.$token.'?email='.urlencode($email));

        $user->notify(new ResetPasswordNotification($url));
    }

    /** Réinitialise le mot de passe. Renvoie false si le jeton est invalide/expiré. */
    public function reset(int $organisationId, string $email, string $token, string $newPassword): bool
    {
        $record = DB::table('password_reset_tokens')
            ->where('organisation_id', $organisationId)
            ->where('email', $email)
            ->first();

        if ($record === null) {
            return false;
        }

        $expired = Carbon::parse($record->created_at)->lt(now()->subMinutes($this->expiresMinutes));
        $matches = hash_equals($record->token, hash('sha256', $token));

        if ($expired || ! $matches) {
            if ($expired) {
                $this->deleteToken($organisationId, $email);
            }

            return false;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return false;
        }

        // Le cast 'hashed' applique Argon2id.
        $user->forceFill([
            'password' => $newPassword,
            'remember_token' => Str::random(60),
        ])->save();

        // Usage unique.
        $this->deleteToken($organisationId, $email);

        return true;
    }

    private function deleteToken(int $organisationId, string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('organisation_id', $organisationId)
            ->where('email', $email)
            ->delete();
    }
}
