<?php

namespace App\Domain\Fleet;

/**
 * Type d'un champ de protocole de service.
 */
enum ProtocolFieldType: string
{
    case SECTION = 'section';   // titre de section (pas de saisie, regroupe les champs suivants)
    case TEXT = 'text';         // saisie libre
    case NUMBER = 'number';     // valeur chiffrée
    case GAUGE = 'gauge';       // jauge (curseur min/max, ex. niveau O2, carburant)
    case TRISTATE = 'tristate'; // vide / OK / NOK
    case CHECKBOX = 'checkbox'; // case à cocher
    case PHOTO = 'photo';       // photo

    public function label(): string
    {
        return match ($this) {
            self::SECTION => 'Titre de section',
            self::TEXT => 'Texte libre',
            self::NUMBER => 'Valeur chiffrée',
            self::GAUGE => 'Jauge',
            self::TRISTATE => 'Contrôle (vide / OK / NOK)',
            self::CHECKBOX => 'Case à cocher',
            self::PHOTO => 'Photo',
        };
    }

    /** Le champ porte une valeur numérique (seuils d'alerte possibles). */
    public function isNumeric(): bool
    {
        return $this === self::NUMBER || $this === self::GAUGE;
    }

    /** Le champ attend une réponse de l'agent (une section n'est qu'un titre). */
    public function isInput(): bool
    {
        return $this !== self::SECTION;
    }

    /** @return list<array{value:string,label:string,numeric:bool,input:bool}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'numeric' => $t->isNumeric(),
            'input' => $t->isInput(),
        ], self::cases());
    }
}
