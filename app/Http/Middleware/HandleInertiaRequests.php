<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = app(TenantContext::class)->organisation();
        // Toujours l'utilisateur métier (guard web), jamais l'exploitant plateforme.
        $user = Auth::guard('web')->user();

        return [
            ...parent::share($request),
            // Utilisateur authentifié (données minimales, jamais de secret).
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'grade' => $user->grade,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            // Organisation courante (personnalisation + branding par secteur).
            'tenant' => $tenant ? [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'settings' => $tenant->settings,
                'profile' => $tenant->profile()->toArray(),
            ] : null,
            // Exploitant plateforme (Desk) — guard séparé du métier.
            'platformAuth' => [
                'admin' => Auth::guard('platform')->check() ? [
                    'id' => Auth::guard('platform')->id(),
                    'name' => Auth::guard('platform')->user()->name,
                    'email' => Auth::guard('platform')->user()->email,
                ] : null,
            ],
            // Messages flash (confirmation / erreur).
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
