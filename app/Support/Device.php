<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Détection sommaire du type d'appareil à partir du User-Agent, pour orienter
 * l'utilisateur vers l'application terrain (mobile) plutôt que le bureau.
 */
final class Device
{
    /** Détecte un téléphone (on exclut les tablettes, qui gardent le bureau). */
    public static function isMobile(Request $request): bool
    {
        $ua = (string) $request->userAgent();

        if ($ua === '') {
            return false;
        }

        // Tablettes → expérience bureau.
        if (preg_match('/iPad|Tablet/i', $ua)) {
            return false;
        }

        return (bool) preg_match('/Mobile|Android|iPhone|iPod|IEMobile|BlackBerry|Opera Mini/i', $ua);
    }
}
