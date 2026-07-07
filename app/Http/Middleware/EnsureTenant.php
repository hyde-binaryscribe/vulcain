<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantit qu'une organisation a été résolue (routes métier servies uniquement
 * sur un sous-domaine d'organisation). Le domaine central relève de l'espace
 * plateforme, traité séparément.
 */
class EnsureTenant
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->tenant->check(), 404);

        return $next($request);
    }
}
