<?php

namespace App\Support\Tenancy;

use App\Models\Organisation;
use Spatie\Permission\PermissionRegistrar;

/**
 * Contexte de location courant (organisation active) pour la requête / le process.
 *
 * Enregistré en singleton. Le middleware ResolveTenant le renseigne pour les
 * requêtes servies sur un sous-domaine d'organisation. Le scope global
 * OrganisationScope s'appuie dessus pour cloisonner toutes les lectures ; le
 * contexte « team » de spatie/laravel-permission est synchronisé en parallèle
 * pour que les rôles/permissions soient cloisonnés par organisation.
 */
class TenantContext
{
    protected ?Organisation $organisation = null;

    /** Autorise temporairement les requêtes inter-tenant (opérations plateforme explicites). */
    protected bool $crossTenant = false;

    public function set(Organisation $organisation): static
    {
        $this->organisation = $organisation;
        $this->crossTenant = false;
        $this->syncPermissionTeam($organisation->id);

        return $this;
    }

    public function forget(): void
    {
        $this->organisation = null;
        $this->syncPermissionTeam(null);
    }

    public function check(): bool
    {
        return $this->organisation !== null;
    }

    public function organisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function id(): ?int
    {
        return $this->organisation?->id;
    }

    public function isCrossTenant(): bool
    {
        return $this->crossTenant;
    }

    /**
     * Exécute un callback dans le contexte d'une organisation donnée,
     * puis restaure l'état précédent.
     */
    public function runFor(Organisation $organisation, callable $callback): mixed
    {
        [$prevOrg, $prevCross] = [$this->organisation, $this->crossTenant];
        $this->organisation = $organisation;
        $this->crossTenant = false;
        $this->syncPermissionTeam($organisation->id);

        try {
            return $callback();
        } finally {
            $this->organisation = $prevOrg;
            $this->crossTenant = $prevCross;
            $this->syncPermissionTeam($prevOrg?->id);
        }
    }

    /**
     * Exécute un callback en mode inter-tenant explicite (aucun cloisonnement).
     * Réservé aux opérations plateforme légitimes.
     */
    public function runCrossTenant(callable $callback): mixed
    {
        [$prevOrg, $prevCross] = [$this->organisation, $this->crossTenant];
        $this->organisation = null;
        $this->crossTenant = true;
        $this->syncPermissionTeam(null);

        try {
            return $callback();
        } finally {
            $this->organisation = $prevOrg;
            $this->crossTenant = $prevCross;
            $this->syncPermissionTeam($prevOrg?->id);
        }
    }

    /** Aligne le contexte « team » de spatie sur l'organisation courante. */
    protected function syncPermissionTeam(?int $organisationId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($organisationId);
    }
}
