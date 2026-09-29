<?php

namespace Tests\Unit;

use App\Domain\Hr\FrenchHolidays;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class FrenchHolidaysTest extends TestCase
{
    public function test_fixed_and_movable_holidays_are_detected(): void
    {
        $this->assertTrue(FrenchHolidays::isHoliday(Carbon::parse('2026-01-01')));  // Jour de l'an
        $this->assertTrue(FrenchHolidays::isHoliday(Carbon::parse('2026-05-01')));  // Fête du travail
        $this->assertTrue(FrenchHolidays::isHoliday(Carbon::parse('2026-12-25')));  // Noël
        $this->assertTrue(FrenchHolidays::isHoliday(Carbon::parse('2026-04-06')));  // Lundi de Pâques (Pâques = 5 avr.)
        $this->assertFalse(FrenchHolidays::isHoliday(Carbon::parse('2026-09-08'))); // jour ordinaire
    }

    public function test_working_days_exclude_sundays(): void
    {
        // Lundi 7 → dimanche 13 sept. 2026 : 6 ouvrables (dimanche exclu).
        $this->assertSame(6, FrenchHolidays::workingDaysBetween(
            Carbon::parse('2026-09-07'), Carbon::parse('2026-09-13')
        ));
    }

    public function test_working_days_exclude_public_holidays(): void
    {
        // Jeu 24 → sam 26 déc. 2026 : le 25 (Noël, vendredi) est exclu → 2 ouvrables.
        $this->assertSame(2, FrenchHolidays::workingDaysBetween(
            Carbon::parse('2026-12-24'), Carbon::parse('2026-12-26')
        ));

        // 1er mai (férié) seul → 0 jour ouvrable.
        $this->assertSame(0, FrenchHolidays::workingDaysBetween(
            Carbon::parse('2026-05-01'), Carbon::parse('2026-05-01')
        ));
    }
}
