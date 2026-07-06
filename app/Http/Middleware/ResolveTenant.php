<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout l'organisation courante à partir du sous-domaine.
 *
 * - Domaine central -> espace plateforme, aucun tenant.
 * - Sous-domaine -> organisation correspondante (404 si inconnue, 403 si suspendue).
 */
class ResolveTenant
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $central = config('tenancy.central_domains', ['localhost']);

        $slug = $this->resolveSlug($host, $central);

        // Domaine central (ou hôte non qualifié) : pas de tenant.
        if ($slug === null) {
            return $next($request);
        }

        $organisation = Organisation::where('slug', $slug)->first();

        abort_if($organisation === null, 404, 'Organisation introuvable.');
        abort_unless($organisation->isActive(), 403, 'Organisation suspendue.');

        $this->tenant->set($organisation);

        return $next($request);
    }

    /**
     * Extrait le libellé de sous-domaine, ou null si l'hôte est central.
     */
    protected function resolveSlug(string $host, array $central): ?string
    {
        if (in_array($host, $central, true)) {
            return null;
        }

        foreach ($central as $domain) {
            if (str_ends_with($host, '.'.$domain)) {
                $slug = substr($host, 0, -strlen('.'.$domain));

                return $this->normalise($slug);
            }
        }

        // Hôte hors domaines centraux : on considère le premier label comme tenant
        // uniquement s'il existe un vrai sous-domaine (a.b.c...).
        $parts = explode('.', $host);

        return count($parts) > 2 ? $this->normalise($parts[0]) : null;
    }

    protected function normalise(string $slug): ?string
    {
        $slug = trim($slug);

        if ($slug === '' || $slug === 'www') {
            return null;
        }

        return $slug;
    }
}
