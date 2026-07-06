<?php

namespace App\Models\Concerns;

use App\Models\Organisation;
use App\Support\Tenancy\Exceptions\TenancyContextMissingException;
use App\Support\Tenancy\OrganisationScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rend un modèle cloisonné par organisation :
 *  - applique le scope global de filtrage ;
 *  - renseigne automatiquement organisation_id à la création à partir du
 *    contexte courant (jamais depuis une entrée utilisateur / mass assignment).
 */
trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope(new OrganisationScope);

        static::creating(function ($model): void {
            if ($model->getAttribute('organisation_id') !== null) {
                return;
            }

            $context = app(TenantContext::class);

            if (! $context->check()) {
                throw new TenancyContextMissingException(static::class);
            }

            $model->setAttribute('organisation_id', $context->id());
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** Échappatoire explicite : requête sans cloisonnement (à utiliser avec prudence). */
    public function scopeWithoutOrganisationScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OrganisationScope::class);
    }
}
