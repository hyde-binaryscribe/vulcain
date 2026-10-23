<?php

namespace App\Domain\IntraChatel;

use App\Domain\Support\Severity;
use App\Models\OxygenBottle;

/**
 * Formulaires de prise de poste et de fin de service d'intra-Chatel, traduits
 * en contrôles Vulkain (mêmes lignes que les protocoles de service du terrain :
 * label, type, valeur, %, unité, alerte). Chaque ligne porte une référence
 * stable (« ref ») pour qu'intra-Chatel retrouve ses champs à la relecture.
 *
 * Contrat : docs/LIEN-INTRA-CHATEL.md.
 */
class ServiceChecklist
{
    /** Niveau (carburant, oxygène, liquides) en dessous duquel une alerte est posée, en %. */
    public const LOW_LEVEL_PERCENT = 25;

    /** Pression maximale par défaut d'une bouteille inconnue de Vulkain, en bar. */
    public const DEFAULT_PRESSURE_MAX = 200;

    /** Bouteilles d'oxygène au plus, par formulaire. */
    public const MAX_BOTTLES = 4;

    /** Règles de validation communes (oxygène et commentaires). */
    public static function sharedRules(): array
    {
        return [
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'oxygen' => ['nullable', 'array', 'max:'.self::MAX_BOTTLES],
            'oxygen.*.position' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_BOTTLES],
            'oxygen.*.serial_number' => ['nullable', 'string', 'max:50'],
            'oxygen.*.pressure_bar' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'oxygen.*.volume_l' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'oxygen.*.capacity_l' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'oxygen.*.sealed' => ['nullable', 'boolean'],
            'comments' => ['nullable', 'array', 'max:20'],
            'comments.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** Règles propres à la prise de poste. */
    public static function openRules(): array
    {
        return self::sharedRules() + [
            'fuel_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fluids' => ['nullable', 'array'],
            'fluids.hot_engine' => ['nullable', 'boolean'],
            'fluids.coolant_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fluids.oil_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fluids.oil_added' => ['nullable', 'boolean'],
            'fluids.tyre_pressure_done' => ['nullable', 'boolean'],
            'bag' => ['nullable', 'array'],
            'bag.name' => ['nullable', 'string', 'max:100'],
            'bag.seal_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** Contrôles de la prise de poste. */
    public static function openRows(array $input, ?callable $specs = null): array
    {
        $fluids = (array) ($input['fluids'] ?? []);
        $bag = (array) ($input['bag'] ?? []);

        return [
            self::gauge('fuel_percent', 'Niveau de carburant', $input['fuel_percent'] ?? null, 'Carburant bas'),
            ...self::oxygenRows($input, $specs),
            self::checkbox('fluids.hot_engine', 'Véhicule chaud', $fluids['hot_engine'] ?? null),
            self::gauge('fluids.coolant_percent', 'Liquide de refroidissement', $fluids['coolant_percent'] ?? null, 'Liquide de refroidissement bas'),
            self::gauge('fluids.oil_percent', 'Niveau d’huile', $fluids['oil_percent'] ?? null, 'Niveau d’huile bas'),
            self::checkbox('fluids.oil_added', 'Apport d’huile', $fluids['oil_added'] ?? null),
            self::checkbox('fluids.tyre_pressure_done', 'Pression des pneus faite', $fluids['tyre_pressure_done'] ?? null),
            self::text('bag.name', 'Sac assigné', $bag['name'] ?? null),
            self::text('bag.seal_number', 'N° de scellé sac / mallette', isset($bag['seal_number']) ? mb_strtoupper(trim((string) $bag['seal_number'])) : null),
        ];
    }

    /** Contrôles de la fin de service (oxygène). */
    public static function closeRows(array $input, ?callable $specs = null): array
    {
        return self::oxygenRows($input, $specs);
    }

    /** Commentaires ajoutés un par un dans le formulaire, réunis en une note. */
    public static function notes(array $input): ?string
    {
        $lines = collect((array) ($input['comments'] ?? []))
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->values();

        return $lines->isEmpty() ? null : mb_substr($lines->implode("\n"), 0, 2000);
    }

    /**
     * Bouteilles : n° de série (majuscules), niveau, scellée. Position = rang dans la liste si absente.
     *
     * Le curseur d'intra-Chatel donne la pression (pressure_bar) ou le volume de gaz restant
     * (volume_l). Le pourcentage se calcule sur la plage de pression de la bouteille ; un volume
     * est d'abord ramené en pression par la capacité en eau (volume ≈ capacité × pression).
     * Sans capacité connue, un volume reste sans pourcentage ni alerte.
     *
     * @param  callable|null  $specs  fn (?string $serial, array $bottle): array{capacity_l: ?float, pressure_min: int, pressure_max: int}
     */
    private static function oxygenRows(array $input, ?callable $specs = null): array
    {
        $rows = [];
        foreach (array_values((array) ($input['oxygen'] ?? [])) as $i => $bottle) {
            $bottle = (array) $bottle;
            $n = (int) ($bottle['position'] ?? $i + 1);
            $serial = isset($bottle['serial_number']) && trim((string) $bottle['serial_number']) !== ''
                ? mb_strtoupper(trim((string) $bottle['serial_number']))
                : null;
            $spec = ($specs ?? fn () => [])($serial, $bottle) + ['capacity_l' => null, 'pressure_min' => 0, 'pressure_max' => self::DEFAULT_PRESSURE_MAX];

            $pressure = self::number($bottle['pressure_bar'] ?? null);
            $volume = self::number($bottle['volume_l'] ?? null);
            $capacity = $spec['capacity_l'] !== null && (float) $spec['capacity_l'] > 0 ? (float) $spec['capacity_l'] : null;
            $basis = $pressure ?? ($volume !== null && $capacity !== null ? $volume / $capacity : null);
            $percent = $basis === null ? null : OxygenBottle::percentOf((int) round($basis), (int) $spec['pressure_min'], (int) $spec['pressure_max']);
            $alert = $percent !== null && $percent <= self::LOW_LEVEL_PERCENT
                ? ['severity' => Severity::WARNING->value, 'message' => "Bouteille O2 {$n} presque vide ({$percent} %)"]
                : null;

            $rows[] = self::text("oxygen.{$n}.serial_number", "Bouteille O2 {$n} — n° de série", $serial);
            // Pression : relevée, ou ligne vide (« À SAISIR ») si le curseur n'a donné aucun niveau.
            if ($pressure !== null || $volume === null) {
                $rows[] = self::row("oxygen.{$n}.pressure_bar", "Bouteille O2 {$n} — pression", 'gauge', $pressure,
                    ['percent' => $percent, 'unit' => 'bar', 'share' => 'o2', 'alert' => $alert]);
            }
            if ($volume !== null) {
                $rows[] = self::row("oxygen.{$n}.volume_l", "Bouteille O2 {$n} — volume", 'gauge', $volume,
                    ['percent' => $percent, 'unit' => 'L', 'share' => $pressure === null ? 'o2' : null, 'alert' => $pressure === null ? $alert : null]);
            }
            $rows[] = self::checkbox("oxygen.{$n}.sealed", "Bouteille O2 {$n} — scellée", $bottle['sealed'] ?? null);
        }

        return $rows;
    }

    /** Nombre saisi (entier si possible), ou null. */
    private static function number(mixed $value): int|float|null
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value == (int) $value ? (int) $value : round((float) $value, 1);
    }

    private static function gauge(string $ref, string $label, mixed $value, string $alertMessage, ?string $share = null): array
    {
        $percent = $value === null || $value === '' ? null : (int) $value;

        return self::row($ref, $label, 'gauge', $percent, [
            'percent' => $percent,
            'unit' => '%',
            'share' => $share,
            'alert' => $percent !== null && $percent <= self::LOW_LEVEL_PERCENT
                ? ['severity' => Severity::WARNING->value, 'message' => "{$alertMessage} ({$percent} %)"]
                : null,
        ]);
    }

    private static function checkbox(string $ref, string $label, mixed $value): array
    {
        return self::row($ref, $label, 'checkbox', $value === null ? null : (bool) $value);
    }

    private static function text(string $ref, string $label, mixed $value): array
    {
        $value = $value === null ? null : trim((string) $value);

        return self::row($ref, $label, 'text', $value === '' ? null : $value);
    }

    private static function row(string $ref, string $label, string $type, mixed $value, array $extra = []): array
    {
        return [
            'ref' => $ref,
            'label' => $label,
            'type' => $type,
            'value' => $value,
            'percent' => $extra['percent'] ?? null,
            'unit' => $extra['unit'] ?? null,
            'share' => $extra['share'] ?? null,
            'photo_path' => null,
            'alert' => $extra['alert'] ?? null,
        ];
    }
}
