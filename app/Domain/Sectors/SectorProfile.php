<?php

namespace App\Domain\Sectors;

/**
 * Profil d'un secteur : vocabulaire, branding et presets. Volontairement léger
 * (point d'extension) ; le contenu détaillé (catalogues, types de véhicules…)
 * s'ajoutera par secteur sans toucher au cœur.
 */
final class SectorProfile
{
    public function __construct(
        public readonly Sector $sector,
        public readonly string $label,      // libellé du secteur
        public readonly string $unitLabel,  // unité opérationnelle (caserne / société / antenne)
        public readonly string $tagline,    // sous-titre d'interface
        public readonly string $themeColor, // couleur d'accent (branding)
    ) {}

    public static function for(Sector $sector): self
    {
        return match ($sector) {
            Sector::SDIS => new self(
                $sector,
                'Sapeurs-pompiers',
                'Centre de secours',
                'Inventaire opérationnel — sapeurs-pompiers',
                '#991b1b', // rouge foncé
            ),
            Sector::AMBULANCE_PRIVEE => new self(
                $sector,
                'Ambulance privée',
                'Société',
                'Inventaire opérationnel — transport sanitaire',
                '#1d4ed8', // bleu
            ),
            Sector::AASC => new self(
                $sector,
                'Sécurité civile',
                'Antenne',
                'Inventaire opérationnel — sécurité civile',
                '#c2410c', // orange
            ),
        };
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'sector' => $this->sector->value,
            'label' => $this->label,
            'unit_label' => $this->unitLabel,
            'tagline' => $this->tagline,
            'theme' => $this->themeColor,
        ];
    }
}
