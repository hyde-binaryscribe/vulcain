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
