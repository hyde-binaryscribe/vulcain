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
    /**
     * Dernier lien d'acceptation généré (jeton en clair). Exposé pour permettre
     * à l'administrateur de le transmettre lui-même si l'e-mail n'arrive pas.
     */
    public ?string $lastAcceptUrl = null;

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

        $this->lastAcceptUrl = $this->acceptUrl($token, $email);

        Notification::route('mail', $email)->notify(
            new OrganisationInvitationNotification(
                $organisation->name,
                $this->lastAcceptUrl,
                $this->expiresMinutes,
                Rbac::ROLE_LABELS[$role] ?? $role,
            )
        );

        return $invitation;
    }

    /** Construit le lien d'acceptation sur l'hôte applicatif (compte unique). */
    public function acceptUrl(string $token, string $email): string
    {
        $appDomain = config('tenancy.app_domains')[0] ?? null;
        if ($appDomain !== null) {
            $base = 'https://'.$appDomain;
        } else {
            $request = request();
            $base = ($request?->getScheme() ?: 'http').'://'.($request?->getHttpHost() ?: 'localhost');
        }

        return $base.'/accept-invitation/'.$token.'?email='.urlencode($email);
    }

    /** Invitation en attente correspondant à un jeton brut (affichage). */
    public function pendingByToken(string $token): ?Invitation
    {
        return $this->tenant->runCrossTenant(fn () => Invitation::query()
            ->where('token', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->first());
    }

    /**
     * Accepte une invitation : crée l'utilisateur avec le rôle prévu.
     * L'organisation est déduite du jeton. Renvoie null si invalide/expiré.
     *
     * @param  array{first_name:string,last_name:string,password:string}  $data
     */
    public function accept(string $email, string $token, array $data): ?User
    {
        $email = mb_strtolower(trim($email));

        // Résolution par jeton (cohérente avec l'affichage GET) : le lien cliqué
        // détermine l'invitation, pas « la dernière en attente pour cet e-mail »
        // — sinon un ancien lien pouvait viser une invitation différente.
        $invitation = $this->tenant->runCrossTenant(fn () => Invitation::query()
            ->where('token', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->first());

        if ($invitation === null || $invitation->expires_at->isPast()) {
            return null;
        }

        // L'e-mail saisi doit correspondre à celui de l'invitation.
        if (! hash_equals($invitation->email, $email)) {
            return null;
        }

        $organisation = $invitation->organisation;

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
