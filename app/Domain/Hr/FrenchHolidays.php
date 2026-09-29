<?php

namespace App\Domain\Hr;

use Illuminate\Support\Carbon;

/**
 * Jours fériés français (métropole) et décompte des jours ouvrables.
 *
 * « Jours ouvrables » = tous les jours de la semaine sauf le dimanche et les
 * jours fériés (soit, en pratique, du lundi au samedi hors fériés). C'est la
 * base légale de décompte des congés payés en France.
 */
final class FrenchHolidays
{
    /**
     * Jours fériés d'une année, indexés par date (Y-m-d).
     *
     * @return array<string, true>
     */
    public static function forYear(int $year): array
    {
        $easter = self::easterSunday($year);

        $dates = [
            Carbon::create($year, 1, 1),      // Jour de l'an
            $easter->copy()->addDay(),        // Lundi de Pâques
            Carbon::create($year, 5, 1),      // Fête du travail
            Carbon::create($year, 5, 8),      // Victoire 1945
            $easter->copy()->addDays(39),     // Ascension
            $easter->copy()->addDays(50),     // Lundi de Pentecôte
            Carbon::create($year, 7, 14),     // Fête nationale
            Carbon::create($year, 8, 15),     // Assomption
            Carbon::create($year, 11, 1),     // Toussaint
            Carbon::create($year, 11, 11),    // Armistice 1918
            Carbon::create($year, 12, 25),    // Noël
        ];

        $out = [];
        foreach ($dates as $d) {
            $out[$d->toDateString()] = true;
        }

        return $out;
    }

    public static function isHoliday(Carbon $date): bool
    {
        return isset(self::forYear((int) $date->year)[$date->toDateString()]);
    }

    /**
     * Nombre de jours ouvrables entre deux dates (bornes incluses) : lundi à
     * samedi, hors dimanches et jours fériés.
     */
    public static function workingDaysBetween(Carbon $start, Carbon $end): int
    {
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        $count = 0;
        $cache = [];

        while ($cursor->lte($last)) {
            $year = (int) $cursor->year;
            $cache[$year] ??= self::forYear($year);

            $isSunday = $cursor->dayOfWeek === Carbon::SUNDAY;
            $isHoliday = isset($cache[$year][$cursor->toDateString()]);

            if (! $isSunday && ! $isHoliday) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }

    /** Dimanche de Pâques (algorithme de Meeus/Jones/Butcher, calendrier grégorien). */
    private static function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
