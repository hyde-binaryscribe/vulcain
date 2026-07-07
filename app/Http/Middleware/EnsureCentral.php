<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route au domaine central (espace plateforme) : refuse l'accès
 * si une organisation a été résolue (sous-domaine).
 */
class EnsureCentral
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->tenant->check(), 404);

        return $next($request);
    }
}
