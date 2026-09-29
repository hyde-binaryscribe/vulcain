<?php

namespace App\Http\Controllers;

use App\Domain\Events\EcheanceEvents;
use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Déclencheur de tâches planifiées par URL, pour les hébergements dont le shell
 * des tâches Plesk est restreint (chroot sans PHP CLI) : la tâche « Récupérer
 * une URL » appelle ces endpoints, protégés par un jeton secret.
 */
class CronController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** Génère/clôture les événements d'échéance de toutes les organisations. */
    public function echeances(Request $request): Response
    {
        $this->authorizeToken($request);

        $created = 0;
        $closed = 0;

        Organisation::query()->orderBy('id')->each(function (Organisation $org) use (&$created, &$closed) {
            $result = $this->tenant->runFor($org, fn () => EcheanceEvents::generate($org));
            $created += $result['created'];
            $closed += $result['closed'];
        });

        return response("OK — {$created} créé(s), {$closed} clôturé(s).\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Vérifie le jeton (query « token » ou en-tête « X-Cron-Token »). Endpoint
     * désactivé (404) si aucun jeton n'est configuré, pour ne pas l'exposer.
     */
    private function authorizeToken(Request $request): void
    {
        $expected = (string) config('security.cron.token', '');
        // Jeton accepté depuis le chemin (/cron/echeances/{token}), la query
        // (?token=) ou l'en-tête X-Cron-Token.
        $provided = (string) ($request->route('token')
            ?? $request->query('token')
            ?? $request->header('X-Cron-Token', ''));

        abort_if($expected === '' || ! hash_equals($expected, $provided), 404);
    }
}
