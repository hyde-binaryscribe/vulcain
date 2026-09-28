<?php

namespace App\Domain\Sectors;

/**
 * Profil d'un secteur : vocabulaire, branding et presets. Volontairement léger
 * (point d'extension) ; le contenu détaillé (catalogues, types de véhicules…)
 * s'ajoutera par secteur sans toucher au cœur.
 *
 * Vocabulaire du niveau « site » selon le secteur :
 *  - SDIS       : organisation = SDIS,     site = « Centre de secours »
 *  - Ambulance  : organisation = Société,  site = « Site »
 *  - AASC       : organisation = Structure, site = « Antenne »
 */
final class SectorProfile
{
    public function __construct(
        public readonly Sector $sector,
        public readonly string $label,           // libellé du secteur
        public readonly string $orgLabel,        // niveau organisation (SDIS / Société / Structure)
        public readonly string $siteLabel,       // niveau site, au singulier
        public readonly string $siteLabelPlural, // niveau site, au pluriel
        public readonly string $tagline,         // sous-titre d'interface
        public readonly string $themeColor,      // couleur d'accent (branding)
    ) {}

    public static function for(Sector $sector): self
    {
        return match ($sector) {
            Sector::SDIS => new self(
                $sector,
                'Sapeurs-pompiers',
                'SDIS',
                'Centre de secours',
                'Centres de secours',
                'Inventaire opérationnel — sapeurs-pompiers',
                '#991b1b', // rouge foncé
            ),
            Sector::AMBULANCE_PRIVEE => new self(
                $sector,
                'Ambulance privée',
                'Société',
                'Site',
                'Sites',
                'Inventaire opérationnel — transport sanitaire',
                '#1d4ed8', // bleu
            ),
            Sector::AASC => new self(
                $sector,
                'Sécurité civile',
                'Structure',
                'Antenne',
                'Antennes',
                'Inventaire opérationnel — sécurité civile',
                '#c2410c', // orange
            ),
        };
    }

    /**
     * Types de site proposés selon le secteur (le premier est la valeur par
     * défaut). « autre » reste toujours disponible en repli.
     *
     * @return list<array{value:string,label:string}>
     */
    public function siteKinds(): array
    {
        $kinds = match ($this->sector) {
            Sector::SDIS => [
                ['value' => 'centre', 'label' => 'Centre de secours'],
                ['value' => 'groupement', 'label' => 'Groupement'],
                ['value' => 'depot', 'label' => 'Dépôt / magasin'],
            ],
            Sector::AMBULANCE_PRIVEE => [
                ['value' => 'site', 'label' => 'Site d’exploitation'],
                ['value' => 'antenne', 'label' => 'Antenne'],
                ['value' => 'depot', 'label' => 'Dépôt / garage'],
            ],
            Sector::AASC => [
                ['value' => 'antenne', 'label' => 'Antenne'],
                ['value' => 'poste', 'label' => 'Poste de secours'],
                ['value' => 'depot', 'label' => 'Local / dépôt'],
            ],
        };

        $kinds[] = ['value' => 'autre', 'label' => 'Autre'];

        return $kinds;
    }

    /** @return list<string> */
    public function siteKindValues(): array
    {
        return array_map(fn (array $k) => $k['value'], $this->siteKinds());
    }

    /**
     * Types de véhicule suggérés selon le secteur. Le champ reste libre :
     * ce sont des propositions (liste déroulante), pas une contrainte.
     *
     * @return list<string>
     */
    public function vehicleTypes(): array
    {
        return match ($this->sector) {
            Sector::SDIS => ['VSAV', 'FPT', 'FPTL', 'CCF', 'VSR', 'EPA', 'VTU', 'VLCG', 'VL'],
            Sector::AMBULANCE_PRIVEE => ['Ambulance type A', 'Ambulance type B', 'Ambulance type C', 'VSL', 'ASSU'],
            Sector::AASC => ['VPSP', 'VL', 'VLHR', 'VTP', 'Poste de secours mobile'],
        };
    }

    /**
     * Protocoles de désinfection pré-remplis selon les niveaux recommandés
     * (cadre ARS / bionettoyage du transport sanitaire). Fournis uniquement pour
     * l'ambulance privée ; ce sont des MODÈLES à adapter à votre protocole
     * d'établissement (produits, temps de contact, EPI selon vos procédures).
     *
     * @return list<array{name:string,type:string,cadence:string,frequency_days:?int,procedure:string}>
     */
    public function disinfectionProtocols(): array
    {
        if ($this->sector !== Sector::AMBULANCE_PRIVEE) {
            return [];
        }

        return [
            [
                'name' => 'Entretien courant (après chaque transport)',
                'type' => 'nettoyage_courant',
                'cadence' => 'Après chaque transport',
                'frequency_days' => null,
                'procedure' => implode("\n", [
                    'Aérer la cellule sanitaire.',
                    'Mettre des gants à usage unique (EPI adaptés).',
                    'Éliminer les déchets ; trier les DASRI dans la filière dédiée.',
                    'Nettoyer-désinfecter les surfaces en contact : brancard, barres de maintien, poignées, accoudoirs, plans de travail.',
                    'Utiliser un détergent-désinfectant de surface conforme (bactéricide/virucide, norme EN 14476).',
                    'Réfection du brancard : drap propre.',
                    'Retirer les gants, hygiène des mains.',
                ]),
            ],
            [
                'name' => 'Bionettoyage quotidien (fin de service)',
                'type' => 'desinfection',
                'cadence' => 'Quotidien',
                'frequency_days' => 1,
                'procedure' => implode("\n", [
                    'EPI adaptés + hygiène des mains.',
                    'Évacuation des déchets et du linge sale (filières dédiées).',
                    'Bionettoyage complet de la cellule : sol, parois, plafond, surfaces et rangements.',
                    'Nettoyage-désinfection des dispositifs médicaux réutilisables et supports.',
                    'Détergent-désinfectant conforme, respect du temps de contact indiqué.',
                    'Réapprovisionnement et remise en ordre.',
                    'Traçabilité : consigner l’opération.',
                ]),
            ],
            [
                'name' => 'Désinfection renforcée (patient à risque infectieux / hebdomadaire)',
                'type' => 'bio_nettoyage',
                'cadence' => 'Hebdomadaire ou après patient à risque',
                'frequency_days' => 7,
                'procedure' => implode("\n", [
                    'EPI renforcés (masque, surblouse, lunettes si besoin) + hygiène des mains.',
                    'Isolement des déchets et du linge selon la filière contaminée.',
                    'Bionettoyage renforcé de l’ensemble de la cellule et des dispositifs.',
                    'Produit sporicide/virucide conforme (EN 14476), temps de contact strictement respecté.',
                    'Désinfection complémentaire (voie aérienne / brumisation) selon votre protocole d’établissement.',
                    'Contrôle visuel, remise en service après séchage.',
                    'Traçabilité renforcée : opérateur, produit, motif.',
                ]),
            ],
        ];
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'sector' => $this->sector->value,
            'label' => $this->label,
            'org_label' => $this->orgLabel,
            'site_label' => $this->siteLabel,
            'site_label_plural' => $this->siteLabelPlural,
            'tagline' => $this->tagline,
            'theme' => $this->themeColor,
        ];
    }
}
