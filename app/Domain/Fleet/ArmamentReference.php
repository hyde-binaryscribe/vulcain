<?php

namespace App\Domain\Fleet;

/**
 * Modèles d'armement de référence (brouillon) dérivés de la norme NF EN 1789 et
 * de l'arrêté du 12 décembre 2017 — à affiner par l'organisation. L'oxygène est
 * laissé « à préciser » (volumes et conditionnements variant selon le type).
 *
 * Les types B et C reprennent le socle du type A, enrichi. La liste qui fait foi
 * reste l'annexe 2 de l'arrêté ; ces modèles ne sont qu'un point de départ.
 */
class ArmamentReference
{
    private const O = 'obligatoire';
    private const C = 'conditionnel';

    /**
     * @return array<string, array{label:string, items:list<array<string,mixed>>}>
     */
    public static function templates(): array
    {
        return [
            'vsl' => ['label' => 'VSL (catégorie D)', 'items' => self::format(self::vsl())],
            'type_a' => ['label' => 'Ambulance Type A (catégorie C) — transport', 'items' => self::format(self::typeA())],
            'type_b' => ['label' => 'Ambulance Type B (catégorie A) — ASSU', 'items' => self::format(self::typeB())],
            'type_c' => ['label' => 'Ambulance Type C (catégorie A) — UMH / SMUR', 'items' => self::format(self::typeC())],
        ];
    }

    /** Lignes brutes : [catégorie, désignation, quantité, niveau, note]. */
    private static function vsl(): array
    {
        return [
            ['Hygiène / protection', 'Gants à usage unique (boîte)', 2, self::O, null],
            ['Hygiène / protection', 'Solution hydro-alcoolique', 1, self::O, null],
            ['Hygiène / protection', 'Container DASRI', 1, self::O, null],
            ['Hygiène / protection', 'Draps à usage unique', 1, self::O, null],
            ['Soins', 'Trousse de premiers secours', 1, self::O, null],
            ['Divers', 'Couverture isotherme', 1, self::O, null],
            ['Sécurité', 'Extincteur', 1, self::O, null],
            ['Sécurité', 'Gilet haute visibilité', 1, self::O, null],
            ['Sécurité', 'Triangle de présignalisation', 1, self::O, null],
        ];
    }

    private static function typeA(): array
    {
        return [
            ['Relevage / brancardage', 'Brancard principal', 1, self::O, 'EN 1865'],
            ['Relevage / brancardage', 'Portoir chaise', 1, self::O, null],
            ['Relevage / brancardage', 'Portoir souple ou scoop', 1, self::O, null],
            ['Immobilisation', 'Jeu de colliers cervicaux (adulte + enfant)', 1, self::O, null],
            ['Immobilisation', 'Attelles de membres (2 supérieurs + 2 inférieurs)', 1, self::O, null],
            ['Ventilation / Oxygène', 'Insufflateur manuel adulte (BAVU)', 1, self::O, null],
            ['Ventilation / Oxygène', 'Insufflateur manuel pédiatrique', 1, self::O, null],
            ['Ventilation / Oxygène', 'Aspirateur de mucosités', 1, self::O, null],
            ['Ventilation / Oxygène', 'Canules oro-pharyngées (jeu de Guedel)', 1, self::O, null],
            ['Monitorage / diagnostic', 'Tensiomètre automatique', 1, self::O, 'Obligatoire type A depuis l\'arrêté du 20/04/2026'],
            ['Monitorage / diagnostic', 'Oxymètre de pouls (SpO2)', 1, self::O, 'Obligatoire type A depuis l\'arrêté du 20/04/2026'],
            ['Monitorage / diagnostic', 'Lecteur de glycémie', 1, self::O, 'Obligatoire type A depuis l\'arrêté du 20/04/2026'],
            ['Monitorage / diagnostic', 'Stéthoscope', 1, self::O, null],
            ['Monitorage / diagnostic', 'Thermomètre', 1, self::O, null],
            ['Hygiène / protection', 'Gants à usage unique (boîte)', 2, self::O, null],
            ['Hygiène / protection', 'Solution hydro-alcoolique', 1, self::O, null],
            ['Hygiène / protection', 'Container DASRI', 1, self::O, null],
            ['Hygiène / protection', 'Draps à usage unique', 1, self::O, null],
            ['Hygiène / protection', 'Couverture bactériostatique / isotherme', 1, self::O, null],
            ['Hygiène / protection', 'Haricot', 1, self::O, null],
            ['Hygiène / protection', 'Sacs vomitoires', 1, self::O, null],
            ['Soins / pansements', 'Trousse de secours / pansements', 1, self::O, null],
            ['Soins / pansements', 'Compresses stériles', 1, self::O, null],
            ['Soins / pansements', 'Bandes', 1, self::O, null],
            ['Soins / pansements', 'Ciseaux', 1, self::O, null],
            ['Divers', 'Potence à perfusion', 1, self::O, null],
            ['DSA / DAE', 'Défibrillateur (DSA)', 1, self::C, 'Obligatoire pour ASSU (type B/C)'],
            ['Sécurité', 'Extincteur', 1, self::O, null],
            ['Sécurité', 'Gilet haute visibilité', 1, self::O, null],
            ['Sécurité', 'Triangle de présignalisation', 1, self::O, null],
        ];
    }

    /** Type B = socle A (sans le DSA conditionnel) + équipements d'urgence. */
    private static function typeB(): array
    {
        $base = array_values(array_filter(self::typeA(), fn ($r) => $r[1] !== 'Défibrillateur (DSA)'));

        return array_merge($base, [
            ['DSA / DAE', 'Défibrillateur semi-automatique avec enregistrement ECG', 1, self::O, null],
            ['Relevage / brancardage', 'Matelas à dépression (matelas coquille)', 1, self::O, null],
            ['Immobilisation', 'Attelle cervico-thoracique (type KED)', 1, self::O, null],
        ]);
    }

    /** Type C = socle B + réanimation (l'oxygène renforcé se définit via la dotation O2). */
    private static function typeC(): array
    {
        return array_merge(self::typeB(), [
            ['Réanimation', 'Respirateur de transport', 1, self::O, null],
            ['Réanimation', 'Scope multiparamétrique', 1, self::O, null],
            ['Réanimation', 'Pousse-seringues électriques', 2, self::O, null],
            ['Réanimation', 'Aspirateur électrique', 1, self::O, null],
        ]);
    }

    /**
     * @param  list<array{0:string,1:string,2:int,3:string,4:?string}>  $rows
     * @return list<array<string,mixed>>
     */
    private static function format(array $rows): array
    {
        return array_map(fn (array $r) => [
            'category' => $r[0],
            'designation' => $r[1],
            'quantity' => $r[2],
            'level' => $r[3],
            'note' => $r[4],
            'volume_l' => $r[5] ?? null,
        ], $rows);
    }
}
