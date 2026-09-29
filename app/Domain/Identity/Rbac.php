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

    public const MODERATOR = 'moderateur';

    /** Libellés d'affichage. */
    public const ROLE_LABELS = [
        self::ADMIN => 'Administrateur',
        self::PHARMACY => 'Responsable pharmacie',
        self::MODERATOR => 'Modérateur',
        self::VERIFIER => 'Vérificateur',
    ];

    /** Catalogue des permissions (côté serveur). */
    public const PERMISSIONS = [
        'users.manage',
        'roles.manage',
        'sites.manage',
        'vehicles.manage',
        'locations.manage',
        'catalog.manage',
        'templates.manage',
        'protocols.manage',
        'protocols.perform',
        'pharmacy.manage',
        'anomalies.manage',
        'repairs.manage',
        'disinfections.record',
        'leave.manage',
        'leave.submit_for_others',
        'documents.manage',
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

            // Matériel, stock, protocoles, anomalies, réparations (pas d'admin/sécurité).
            self::PHARMACY => [
                'vehicles.manage',
                'locations.manage',
                'catalog.manage',
                'templates.manage',
                'protocols.manage',
                'protocols.perform',
                'pharmacy.manage',
                'anomalies.manage',
                'repairs.manage',
                'disinfections.record',
                'history.view',
                'stats.view',
                'exports.create',
            ],

            // Vérificateur + dépôt de demandes de congés pour ses collègues
            // (aide à la saisie), SANS pouvoir valider.
            self::MODERATOR => [
                'protocols.perform',
                'anomalies.manage',
                'disinfections.record',
                'leave.submit_for_others',
                'history.view',
            ],

            // Réalisation des protocoles + déclaration d'anomalies + désinfections.
            self::VERIFIER => [
                'protocols.perform',
                'anomalies.manage',
                'disinfections.record',
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
