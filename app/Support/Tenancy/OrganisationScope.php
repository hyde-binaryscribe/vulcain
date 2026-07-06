<?php

namespace App\Support\Tenancy;

use App\Support\Tenancy\Exceptions\TenancyContextMissingException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Scope global appliqué à tout modèle cloisonné (trait BelongsToOrganisation).
 *
 * - Organisation courante définie -> filtre sur organisation_id.
 * - Mode inter-tenant explicite ou console -> aucun filtre (seeders, commandes,
 *   opérations plateforme via runCrossTenant()).
 * - Requête HTTP sans contexte -> exception (empêche toute fuite silencieuse).
 */
class OrganisationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->check()) {
            $builder->where($model->getTable().'.organisation_id', $context->id());

            return;
        }

        if ($context->isCrossTenant() || app()->runningInConsole()) {
            return;
        }

        throw new TenancyContextMissingException($model::class);
    }
}
