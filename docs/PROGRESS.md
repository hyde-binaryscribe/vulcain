# Documentation de progression — Vulcain

Suivi vivant du développement (exigé par le cahier des charges, §35).

## Étape en cours
- **Phase 0 / 1.1 / 1.2 / 1.3** : terminées.
- Prochaine : **Phase 2 — Référentiels** (utilisateurs, véhicules + affectations,
  emplacements, catalogue matériel).

## Phase 1.3 (partie 2) — Espace exploitant « Desk » + invitations (terminée)
- Modèle `PlatformAdmin` + **guard `platform`** (session), strictement séparé du métier ;
  table centrale `platform_admins`.
- **Desk** sur le domaine central (préfixe `/platform`, middleware `central`) : connexion
  exploitant, **tableau des organisations** (secteur, statut, nb utilisateurs), **création
  d'organisation** (choix du secteur), suspension/réactivation.
- **Provisionnement** (`OrganisationProvisioner`) : création + rôles + **invitation du 1er
  administrateur** (transaction).
- **Invitations** (`invitations`, `InvitationService`) : jeton haché SHA-256, usage unique,
  expirable, cloisonné ; lien vers le sous-domaine de l'organisation ; page d'activation
  (l'invité définit son mot de passe → devient administrateur, aucun mot de passe par défaut).
- Commande `vulcain:create-platform-admin` (mot de passe masqué, min. 12 car. mixtes).
- Redirection des invités : `/platform/*` → login Desk ; sinon login tenant.
- **Tests (10 nouveaux, 40 au total, verts)** : accès Desk (central vs tenant, guard séparé),
  provisionnement (rôles + invitation + notification, slug unique), acceptation (devient
  admin, usage unique, expiré, cloisonné par organisation).

## Phase 1.3 (partie 1) — RBAC dynamique + secteurs (terminée)
- **RBAC** via spatie/laravel-permission en mode « teams » : rôles **cloisonnés par
  organisation** (clé `organisation_id`), permissions globales, contexte synchronisé avec
  le `TenantContext`.
- 3 rôles métier : Administrateur (accès complet), Responsable pharmacie, Vérificateur ;
  catalogue de 17 permissions (`app/Domain/Identity/Rbac.php`), provisionnées par organisation.
- Permissions/rôles partagés à l'UI (menu adapté au rôle) ; middleware `permission`/`role`
  (vérif. **côté serveur**).
- **Verticalisation (secteurs)** : champ `secteur` sur l'organisation + abstraction
  `SectorProfile` (SDIS, ambulance privée, AASC) — vocabulaire, sous-titre et **branding
  (couleur d'accent) par secteur**, appliqués via variable CSS `--brand`.
- Commandes : `vulcain:create-user --role=…` et `vulcain:grant-role` (rôle à un utilisateur
  existant), mot de passe masqué.
- Données de démo : 4 organisations sur 3 secteurs (demo, caserne-nord, ambulance-sud,
  protection-civile), rôles provisionnés.
- **Tests (5 RBAC, 30 au total, verts)** : permissions par rôle, admin=toutes, rôles
  cloisonnés par organisation, middleware permission (autorisé/refusé).

## Reste en Phase 1.3 (partie 2)
- Espace **super-admin plateforme** (domaine central) : liste + **provisionnement
  d'organisations** (avec choix du secteur).
- **Invitation du 1er administrateur** (lien sécurisé, il définit son mot de passe).
- Commande `vulcain:create-platform-admin`.

## Phase 1.2 — Authentification (terminée)
- Champs utilisateur étendus (prénom, nom, identifiant, grade, actif/inactif, dernière
  connexion, avatar, suppression logique) ; e-mail et identifiant uniques **par organisation**.
- Connexion / déconnexion **cloisonnées** (Auth::attempt filtré par le scope tenant + `is_active`).
- Rotation de l'ID de session après connexion ; comptes inactifs bloqués.
- **Blocage temporaire** après N échecs via table `login_attempts` (+ rate limiter HTTP).
- Mot de passe oublié → **jeton haché SHA-256, à usage unique, expirable, cloisonné**
  (table `password_reset_tokens` scellée par organisation).
- Changement de mot de passe (vérifie l'actuel), édition de profil (e-mail unique/org).
- **Sessions actives listées et révocables** (pilote base de données).
- Front Inertia/Vue : `GuestLayout`, `AppLayout` (menu latéral + barre — thème sapeurs-pompiers),
  pages Login / ForgotPassword / ResetPassword / Dashboard / Profil.
- Commande sécurisée `vulcain:create-user` (mot de passe saisi masqué, jamais par défaut).
- **Tests (16 nouveaux, 25 au total, verts)** : connexion cloisonnée, échec, cross-tenant,
  compte inactif, lockout, déconnexion, reset (haché, usage unique, expiré, mauvais jeton,
  cloisonné), profil (unicité e-mail/org), changement de mot de passe, révocation de session.

## Phase 1.1 — Multi-tenant (terminée)
- Table centrale `organisations` (slug = sous-domaine, statut, settings JSON, soft delete).
- `users` cloisonné : colonne `organisation_id`, e-mail unique **par organisation**.
- `TenantContext` (singleton) + `OrganisationScope` (scope global) + trait `BelongsToOrganisation`
  (remplit `organisation_id` à la création, jamais en mass assignment).
- Middleware `ResolveTenant` : résolution par sous-domaine (404 inconnue, 403 suspendue),
  domaine central = espace plateforme.
- Garde-fou anti-fuite : lecture d'un modèle cloisonné sans contexte en HTTP → exception.
- Partage Inertia de l'organisation courante + utilisateur (données minimales).
- **Tests (9, tous verts)** : cloisonnement des lectures, blocage IDOR inter-tenant,
  remplissage auto de `organisation_id`, exception hors contexte, modes `runFor`/`runCrossTenant`,
  résolution par sous-domaine (OK / 404 / 403 / central).
- Vérif HTTP live : central 200, tenants connus 200, sous-domaine inconnu 404.

## Fonctionnalités terminées
- Analyse complète du cahier des charges (36 sections) + architecture SaaS (Mission 1).
- **Phase 0 — Socle** :
  - Laravel 13.18 (PHP 8.3), skeleton opérationnel.
  - Front **Inertia + Vue 3 + Tailwind** : build OK (755 modules), page d'accueil rendue via HTTP 200.
  - **Argon2id** configuré et vérifié comme algorithme de hachage par défaut.
  - Stack **Docker** (Nginx + PHP-FPM 8.3 + PostgreSQL 16 + Redis + worker de file + Vite).
  - CI GitHub Actions (composer, npm build, migrations PostgreSQL, Pint, tests).
  - `.env.example` durci (Argon, cookies, sessions en base, hôtes Docker), README d'installation.

## Fichiers créés (Phase 0, principaux)
- `docker/php/Dockerfile`, `docker/php/php.ini`, `docker/nginx/default.conf`, `docker-compose.yml`.
- `.github/workflows/ci.yml`, `README.md`.
- `config/hashing.php` (Argon2id), `bootstrap/app.php` (middleware Inertia).
- `resources/js/app.js`, `resources/js/Pages/Welcome.vue`, `resources/views/app.blade.php`, `vite.config.js`.
- `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`.

## Migrations exécutées
- Migrations Laravel de base (users, cache, jobs, sessions) — vérifiées localement (SQLite).
- Aucune table métier encore créée (elles arrivent en Phase 1).

## Tests ajoutés / résultats
- Vérifications manuelles Phase 0 : build front OK, boot HTTP 200, Argon2id opérationnel.
- Tests automatisés : à compléter dès la Phase 1 (multi-tenant, auth, RBAC).

## Décisions techniques
- Voir `docs/architecture.md` §8.

## Décisions validées (2026-07-06)
- Frontend : Inertia + Vue 3 + Tailwind.
- Isolation : base partagée + `organisation_id`.
- SaaS : structure abonnements/quotas + espace exploitant ; paiement (Stripe) reporté.
- Accès : sous-domaine par organisation + self-host Docker mono-tenant.

## Problèmes connus
- Aucun. En attente du feu vert pour démarrer la Phase 0.

## Prochaine étape
- Après confirmation : **Phase 0 — Initialisation du projet**, puis **Phase 1 — fondation
  multi‑tenant, authentification, RBAC**.
