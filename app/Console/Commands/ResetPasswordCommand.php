<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Réinitialise le mot de passe d'un utilisateur d'organisation (ou d'un
 * administrateur plateforme avec --platform). Mot de passe saisi masqué.
 */
class ResetPasswordCommand extends Command
{
    protected $signature = 'vulcain:reset-password
        {--email= : Adresse e-mail du compte}
        {--organisation= : Slug de l’organisation (utilisateur métier)}
        {--platform : Cibler un administrateur plateforme (Desk) au lieu d’un utilisateur}';

    protected $description = 'Réinitialise le mot de passe d’un compte (saisie masquée).';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->option('email') ?: $this->ask('E-mail du compte')));

        $account = $this->option('platform')
            ? PlatformAdmin::query()->where('email', $email)->first()
            : $this->findTenantUser($email);

        if ($account === null) {
            $this->error('Compte introuvable.');

            return self::FAILURE;
        }

        $password = $this->secret('Nouveau mot de passe (saisie masquée)');
        $confirmation = $this->secret('Confirmez le mot de passe');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $account->password = $password; // le cast « hashed » (Argon2id) s'applique
        $account->save();

        $this->info("Mot de passe réinitialisé pour {$email}.");

        return self::SUCCESS;
    }

    private function findTenantUser(string $email): ?User
    {
        $slug = $this->option('organisation') ?: $this->ask('Slug de l’organisation');
        $organisation = Organisation::query()->where('slug', $slug)->first();

        if ($organisation === null) {
            $this->error("Organisation « {$slug} » introuvable.");

            return null;
        }

        return User::query()->withoutGlobalScopes()
            ->where('organisation_id', $organisation->id)
            ->where('email', $email)
            ->first();
    }
}
