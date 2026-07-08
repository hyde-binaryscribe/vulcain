<?php

namespace App\Support\Sites;

use App\Models\User;

/**
 * Périmètre de sites effectif pour filtrer les listes.
 *
 * Combine le cloisonnement de l'utilisateur (sites auxquels il est rattaché ;
 * aucun = accès à tout) et le site actif choisi dans l'en-tête (session).
 */
class SiteScope
{
    /**
     * @return list<int>|null `null` = aucun filtre (tous les sites).
     */
    public static function forUser(User $user, ?int $currentSiteId): ?array
    {
        $accessible = $user->accessibleSiteIds(); // null = tout

        if ($currentSiteId !== null && ($accessible === null || in_array($currentSiteId, $accessible, true))) {
            return [$currentSiteId];
        }

        return $accessible;
    }
}
