# Documentation base de données — Vulcain

Document vivant. Décrit les tables et relations au fil du développement.

## Convention multi-tenant
Toute table **métier** porte `organisation_id` (FK → `organisations`, indexée) et est
cloisonnée par `OrganisationScope`. Les tables **centrales** (plateforme) ne le sont pas.

## Tables centrales

### organisations
Tenant du SaaS (centre de secours / SDIS).

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | Nom affiché |
| slug | string unique | Sous-domaine (`slug`.vulcain.app) |
| status | string | `active` \| `suspended` (indexé) |
| settings | json null | Personnalisation (logo, thème, options) |
| created_at / updated_at | timestamps | |
| deleted_at | timestamp null | Suppression logique |

## Tables cloisonnées

### users
Membre d'une organisation.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| organisation_id | FK → organisations | `cascadeOnDelete` |
| name | string | |
| email | string | **unique (organisation_id, email)** |
| email_verified_at | timestamp null | |
| password | string | Argon2id |
| remember_token | string | |
| timestamps | | |

> Champs de profil (grade, statut actif/inactif, dernière connexion, photo, soft delete)
> ajoutés en Phase 1.2 (authentification & gestion des utilisateurs).

## Tables techniques (Laravel)
`password_reset_tokens`, `sessions` (révocation), `cache`, `jobs` — de base.
