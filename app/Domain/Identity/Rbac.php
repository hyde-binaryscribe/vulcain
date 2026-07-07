<?php

namespace App\Domain\Identity;

/**
 * Catalogue RBAC : permissions, rôles métier et leur affectation.
 *
 * Le système reste dynamique (rôles/permissions en base, extensibles). Ce
 * catalogue définit l'état initial provisionné pour chaque organisation.
 */
final class Rbac
{
    // Rôles métier (au sein d'une organisation).
    public const ADMIN = 'administrateur';

    public const PHARMACY = 'responsable_pharmacie';

    public const VERIFIER = 'verificateur';

    /** Libellés d'affichage. */
    public const ROLE_LABELS = [
        self::ADMIN => 'Administrateur',
        self::PHARMACY => 'Responsable pharmacie',
        self::VERIFIER => 'Vérificateur',
    ];

    /** Catalogue des permissions (côté serveur). */
    public const PERMISSIONS = [
        'users.manage',
        'roles.manage',
        'vehicles.manage',
        'locations.manage',
        'catalog.manage',
        'templates.manage',
        'inventories.manage',
        'inventories.perform',
        'pharmacy.manage',
        'anomalies.manage',
        'repairs.manage',
        'history.view',
        'history.view_all',
        'audit.view',
        'settings.manage',
        'stats.view',
        'exports.create',
    ];

    /**
     * Affectation initiale rôle -> permissions.
     *
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        return [
            // Accès complet.
            self::ADMIN => self::PERMISSIONS,

            // Matériel, stock, inventaires, anomalies, réparations (pas d'admin/sécurité).
            self::PHARMACY => [
                'vehicles.manage',
                'locations.manage',
                'catalog.manage',
                'templates.manage',
                'inventories.manage',
                'pharmacy.manage',
                'anomalies.manage',
                'repairs.manage',
                'history.view',
                'stats.view',
                'exports.create',
            ],

            // Réalisation des inventaires + déclaration d'anomalies.
            self::VERIFIER => [
                'inventories.perform',
                'anomalies.manage',
                'history.view',
            ],
        ];
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return array_keys(self::ROLE_LABELS);
    }
}
