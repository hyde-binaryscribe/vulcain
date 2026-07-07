<?php

namespace App\Domain\Protocol;

/**
 * Nature d'un protocole. Un modèle peut en cumuler plusieurs (un même passage
 * peut être à la fois un inventaire ET un contrôle véhicule). Extensible.
 */
enum ProtocolType: string
{
    case INVENTAIRE = 'inventaire';
    case VERIFICATION = 'verification';
    case CONTROLE_VEHICULE = 'controle_vehicule';

    public function label(): string
    {
        return match ($this) {
            self::INVENTAIRE => 'Inventaire',
            self::VERIFICATION => 'Vérification',
            self::CONTROLE_VEHICULE => 'Contrôle véhicule',
        };
    }

    /** @return list<array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }

    /** Filtre + normalise une liste brute (issue d'un formulaire) vers des valeurs valides. */
    public static function sanitize(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $valid = array_map(fn (self $t) => $t->value, self::cases());

        return array_values(array_unique(array_filter(
            array_map(fn ($v) => is_string($v) ? $v : null, $values),
            fn ($v) => $v !== null && in_array($v, $valid, true),
        )));
    }

    /**
     * Libellés d'affichage pour une liste de valeurs.
     *
     * @return list<string>
     */
    public static function labelsFor(?array $values): array
    {
        if (empty($values)) {
            return [];
        }

        return array_values(array_map(
            fn (string $v) => self::from($v)->label(),
            self::sanitize($values),
        ));
    }
}
