<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crée un utilisateur dans une organisation. Le mot de passe est saisi de façon
 * masquée (jamais en argument, ni dans les logs, ni dans l'historique du terminal).
 */
class CreateUserCommand extends Command
{
    protected $signature = 'vulcain:create-user
        {--organisation= : Slug (sous-domaine) de l’organisation}
        {--email= : Adresse e-mail}
        {--first-name= : Prénom}
        {--last-name= : Nom}
        {--grade= : Grade (optionnel)}';

    protected $description = 'Crée un utilisateur dans une organisation (mot de passe saisi masqué).';

    public function handle(TenantContext $tenant): int
    {
        $slug = $this->option('organisation') ?: $this->ask('Slug de l’organisation (sous-domaine)');

        $organisation = Organisation::query()->where('slug', $slug)->first();

        if ($organisation === null) {
            $this->error("Organisation « {$slug} » introuvable.");

            return self::FAILURE;
        }

        $firstName = $this->option('first-name') ?: $this->ask('Prénom');
        $lastName = $this->option('last-name') ?: $this->ask('Nom');
        $email = mb_strtolower(trim($this->option('email') ?: $this->ask('E-mail')));
        $grade = $this->option('grade') ?: $this->ask('Grade (optionnel)', null);

        $password = $this->secret('Mot de passe (saisie masquée)');
        $confirmation = $this->secret('Confirmez le mot de passe');

        $validator = Validator::make([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        return $tenant->runFor($organisation, function () use ($organisation, $firstName, $lastName, $email, $grade, $password) {
            if (User::query()->where('email', $email)->exists()) {
                $this->error("Un utilisateur avec l’e-mail « {$email} » existe déjà dans cette organisation.");

                return self::FAILURE;
            }

            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim("{$firstName} {$lastName}"),
                'grade' => $grade ?: null,
                'email' => $email,
                'password' => $password, // le cast 'hashed' applique Argon2id
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $this->info("Utilisateur créé : {$user->email} (organisation « {$organisation->slug} »).");

            return self::SUCCESS;
        });
    }
}
