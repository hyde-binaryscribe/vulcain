<?php

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

/**
 * Levée lorsqu'un modèle cloisonné est lu ou créé sans organisation courante
 * (hors console et hors mode inter-tenant explicite). Garde-fou anti-fuite.
 */
class TenancyContextMissingException extends RuntimeException
{
    public function __construct(string $model)
    {
        parent::__construct(
            "Aucune organisation courante pour le modèle cloisonné [{$model}]. "
            .'Résolvez un tenant (middleware) ou utilisez TenantContext::runFor()/runCrossTenant().'
        );
    }
}
