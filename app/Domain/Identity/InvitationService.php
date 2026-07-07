<?php

namespace App\Domain\Identity;

use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\OrganisationInvitationNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invitations à rejoindre une organisation. Jeton aléatoire, stocké HACHÉ
 * (SHA-256), à usage unique et expirable. Le lien pointe vers le sous-domaine
 * de l'organisation.
 */
class InvitationService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly int $expiresMinutes,
    ) {}

    public function invite(Organisation $organisation, string $email, string $role): Invitation
    {
        $email = mb_strtolower(trim($email));
        $token = Str::random(64);

        $invitation = Invitation::create([
            'organisation_id' => $organisation->id,
            'email' => $email,
            'role' => $role,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($this->expiresMinutes),
        ]);

        Notification::route('mail', $email)->notify(
            new OrganisationInvitationNotification(
                $organisation->name,
                $this->acceptUrl($organisation, $token, $email),
                $this->expiresMinutes,
                Rbac::ROLE_LABELS[$role] ?? $role,
            )
        );

        return $invitation;
    }

    /** Construit le lien d'acceptation sur le sous-domaine de l'organisation. */
    public function acceptUrl(Organisation $organisation, string $token, string $email): string
    {
        $request = request();
        $scheme = $request?->getScheme() ?: 'http';
        $host = $request?->getHttpHost() ?: (config('tenancy.central_domains')[0] ?? 'localhost');

        return "{$scheme}://{$organisation->slug}.{$host}/accept-invitation/{$token}?email=".urlencode($email);
    }

    /**
     * Accepte une invitation : crée l'utilisateur avec le rôle prévu.
     * Renvoie null si le jeton est invalide/expiré.
     *
     * @param  array{first_name:string,last_name:string,password:string}  $data
     */
    public function accept(Organisation $organisation, string $email, string $token, array $data): ?User
    {
        $email = mb_strtolower(trim($email));

        $invitation = Invitation::query()
            ->where('organisation_id', $organisation->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->latest('id')
            ->first();

        if ($invitation === null || $invitation->expires_at->isPast()) {
            return null;
        }

        if (! hash_equals($invitation->token, hash('sha256', $token))) {
            return null;
        }

        return $this->tenant->runFor($organisation, function () use ($invitation, $organisation, $email, $data) {
            app(RoleProvisioner::class)->provision($organisation);

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $email,
                'password' => $data['password'], // cast 'hashed' -> Argon2id
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($invitation->role);

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
