<?php

namespace App\Console\Commands;

use App\Domain\Events\EcheanceEvents;
use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Génère les événements d'échéance (entretien, désinfection, péremptions,
 * documents) pour toutes les organisations. À planifier quotidiennement (Cron
 * Plesk appelant `php artisan schedule:run`, ou directement cette commande).
 */
class GenerateEcheanceEvents extends Command
{
    protected $signature = 'vulcain:generate-echeance-events';

    protected $description = 'Crée/clôture les événements d\'échéance (entretien, désinfection, péremptions, documents).';

    public function handle(TenantContext $tenant): int
    {
        $totalCreated = 0;
        $totalClosed = 0;

        Organisation::query()->orderBy('id')->each(function (Organisation $org) use ($tenant, &$totalCreated, &$totalClosed) {
            try {
                $result = $tenant->runFor($org, fn () => EcheanceEvents::generate($org));
                $totalCreated += $result['created'];
                $totalClosed += $result['closed'];
                $this->line("• {$org->name} : {$result['created']} créé(s), {$result['closed']} clôturé(s).");
            } catch (Throwable $e) {
                $this->error("• {$org->name} : échec — {$e->getMessage()}");
            }
        });

        $this->info("Terminé : {$totalCreated} événement(s) créé(s), {$totalClosed} clôturé(s).");

        return self::SUCCESS;
    }
}
