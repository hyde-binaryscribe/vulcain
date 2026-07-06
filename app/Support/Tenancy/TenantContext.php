<?php

namespace App\Support\Tenancy;

use App\Models\Organisation;

/**
 * Contexte de location courant (organisation active) pour la requête / le process.
 *
 * Enregistré en singleton. Le middleware ResolveTenant le renseigne pour les
 * requêtes servies sur un sous-domaine d'organisation. Le scope global
 * OrganisationScope s'appuie dessus pour cloisonner toutes les lectures.
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

        return $this;
    }

    public function forget(): void
    {
        $this->organisation = null;
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

        try {
            return $callback();
        } finally {
            $this->organisation = $prevOrg;
            $this->crossTenant = $prevCross;
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

        try {
            return $callback();
        } finally {
            $this->organisation = $prevOrg;
            $this->crossTenant = $prevCross;
        }
    }
}
