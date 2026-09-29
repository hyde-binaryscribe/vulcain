<?php

namespace App\Support\Tenancy;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Provider d'authentification Eloquent qui exécute toutes les lectures de
 * comptes en mode inter-tenant.
 *
 * L'authentification (SessionGuard, middleware « guest »/Authenticate, partage
 * Inertia) recharge l'utilisateur depuis la session par son identifiant ou par
 * jeton « remember ». Ces requêtes portent sur un modèle cloisonné
 * (OrganisationScope) : tant que le tenant n'est pas encore résolu — page
 * invité, session obsolète pointant vers un compte supprimé — le scope lèverait
 * TenancyContextMissing. On les enveloppe donc systématiquement en cross-tenant :
 * la résolution du compte ne dépend pas du tenant (elle sert au contraire à le
 * déterminer), et le cloisonnement métier reste assuré partout ailleurs.
 */
class CrossTenantUserProvider extends EloquentUserProvider
{
    public function __construct(
        private readonly TenantContext $tenant,
        \Illuminate\Contracts\Hashing\Hasher $hasher,
        string $model,
    ) {
        parent::__construct($hasher, $model);
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->tenant->runCrossTenant(fn () => parent::retrieveById($identifier));
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return $this->tenant->runCrossTenant(fn () => parent::retrieveByToken($identifier, $token));
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        return $this->tenant->runCrossTenant(fn () => parent::retrieveByCredentials($credentials));
    }
}
