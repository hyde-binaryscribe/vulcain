<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Crée un administrateur de la plateforme (Desk / exploitant SaaS).
 * Mot de passe saisi masqué, jamais par défaut.
 */
class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'vulcain:create-platform-admin {--name=} {--email=}';

    protected $description = 'Crée un administrateur de la plateforme (mot de passe saisi masqué).';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nom');
        $email = mb_strtolower(trim($this->option('email') ?: $this->ask('E-mail')));
        $password = $this->secret('Mot de passe (saisie masquée)');
        $confirmation = $this->secret('Confirmez le mot de passe');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('platform_admins', 'email')],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->mixedCase()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $admin = PlatformAdmin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password, // cast 'hashed' -> Argon2id
        ]);

        $this->info("Administrateur plateforme créé : {$admin->email}");

        return self::SUCCESS;
    }
}
