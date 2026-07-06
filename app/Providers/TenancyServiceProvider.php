<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contexte de location partagé sur toute la durée de la requête / du process.
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        //
    }
}
