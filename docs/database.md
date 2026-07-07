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
| first_name / last_name | string | Identité |
| name | string | Nom d'affichage (prénom + nom) |
| username | string null | Identifiant interne — **unique (organisation_id, username)** |
| grade | string null | |
| email | string | **unique (organisation_id, email)** |
| avatar_path | string null | Photo de profil (stockage privé, phase fichiers) |
| email_verified_at | timestamp null | |
| password | string | Argon2id |
| is_active | bool | Compte actif/inactif (défaut true) |
| last_login_at | timestamp null | |
| remember_token | string | |
| timestamps + deleted_at | | Suppression logique |

### login_attempts
Tentatives de connexion (blocage temporaire + traçabilité).

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| organisation_id | FK null → organisations | `nullOnDelete` |
| email | string indexé | |
| ip_address / user_agent | | |
| successful | bool | |
| created_at | timestamp | Index (organisation, email, created_at) |

### invitations (cloisonnée par organisation_id explicite)
Invitation à rejoindre une organisation (1er admin, utilisateurs invités).

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| organisation_id | FK → organisations | `cascadeOnDelete` |
| email | string | |
| role | string | Rôle attribué à l'acceptation |
| token | string | **haché** (SHA-256), usage unique |
| expires_at | timestamp | |
| accepted_at | timestamp null | |

## Tables centrales additionnelles

### organisations.sector
Colonne `sector` (SDIS / ambulance privée / AASC), défaut SDIS — verticalisation.

### platform_admins
Exploitant de la plateforme (guard `platform`) : `id`, `name`, `email` (unique global),
`password` (Argon2id), `last_login_at`, soft delete.

## RBAC (spatie, mode teams)
`roles` (avec `organisation_id`), `permissions`, `model_has_roles` / `model_has_permissions`
/ `role_has_permissions` (pivots portant `organisation_id`). Rôles cloisonnés par organisation.

## Tables techniques (Laravel)
- **password_reset_tokens** — redéfinie : clé primaire **(organisation_id, email)**,
  `token` **haché**, `created_at`. Cloisonnée par organisation.
- **sessions** — pilote base de données (révocation), `cache`, `jobs`.
