<?php

use App\Http\Middleware\EnsureAppHost;
use App\Http\Middleware\EnsureCentral;
use App\Http\Middleware\EnsureDeskHost;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveTenant::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // ResolveTenant doit s'exécuter APRÈS le démarrage de session (il lit
        // l'utilisateur connecté pour en déduire l'organisation) mais AVANT le
        // middleware d'authentification : `Authenticate` recharge l'utilisateur
        // depuis la session (User::find, cloisonné par OrganisationScope) ; sans
        // tenant déjà résolu, cette requête lèverait TenancyContextMissing.
        // ResolveTenant charge lui-même l'utilisateur en mode inter-tenant (sûr),
        // pose le tenant, et met l'utilisateur en cache sur le guard pour
        // Authenticate. Placé avant AuthenticatesRequests, il reste aussi avant
        // SubstituteBindings (liaisons de route cloisonnées).
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ResolveTenant::class,
        );

        // Les gardes d'hôte (séparation stricte client/Desk) doivent s'exécuter
        // AVANT tout : un accès sur le mauvais hôte doit renvoyer 404 plutôt
        // qu'une redirection de connexion (302).
        $middleware->prependToPriorityList(before: ResolveTenant::class, prepend: EnsureAppHost::class);
        $middleware->prependToPriorityList(before: ResolveTenant::class, prepend: EnsureDeskHost::class);

        // Alias : exige une organisation résolue (routes métier sur sous-domaine)
        // + contrôle des rôles/permissions (spatie), vérifiés côté serveur.
        $middleware->alias([
            'tenant' => EnsureTenant::class,
            'central' => EnsureCentral::class,
            // Séparation stricte par hôte : client -> app uniquement, Desk -> desk uniquement.
            'app_host' => EnsureAppHost::class,
            'desk_host' => EnsureDeskHost::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Redirection des invités : espace plateforme -> login plateforme ; sinon login tenant.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform', 'platform/*')
            ? route('platform.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
