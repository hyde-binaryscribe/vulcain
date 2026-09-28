<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ResolveTenant;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Routing\SortedMiddleware;
use Tests\TestCase;

/**
 * Verrou d'ordre des middlewares.
 *
 * ResolveTenant DOIT s'exécuter avant Authenticate : ce dernier recharge
 * l'utilisateur depuis la session (User::find, cloisonné par OrganisationScope) ;
 * sans tenant déjà résolu, cette requête lève TenancyContextMissing en HTTP.
 * Il doit aussi rester avant SubstituteBindings (liaisons de route cloisonnées).
 *
 * Ce défaut est INVISIBLE aux tests HTTP : OrganisationScope ne lève pas
 * d'exception quand app()->runningInConsole() est vrai (cas de PHPUnit).
 * D'où ce test structurel sur l'ordre trié.
 */
class MiddlewareOrderTest extends TestCase
{
    /** @return list<string> */
    private function sortedMiddlewareForUri(string $uri): array
    {
        $router = app(Router::class);
        $kernel = app(Kernel::class);

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri) {
                $gathered = $router->gatherRouteMiddleware($route);

                return (new SortedMiddleware($kernel->getMiddlewarePriority(), $gathered))->all();
            }
        }

        $this->fail("Route introuvable : {$uri}");
    }

    public function test_resolve_tenant_runs_before_authenticate_and_bindings(): void
    {
        $order = $this->sortedMiddlewareForUri('dashboard');

        $resolve = array_search(ResolveTenant::class, $order, true);
        $auth = array_search(Authenticate::class, $order, true);
        $bindings = array_search(SubstituteBindings::class, $order, true);

        $this->assertNotFalse($resolve, 'ResolveTenant absent de la pile.');
        $this->assertNotFalse($auth, 'Authenticate absent de la pile.');
        $this->assertNotFalse($bindings, 'SubstituteBindings absent de la pile.');

        $this->assertLessThan($auth, $resolve, 'ResolveTenant doit précéder Authenticate.');
        $this->assertLessThan($bindings, $resolve, 'ResolveTenant doit précéder SubstituteBindings.');
    }
}
