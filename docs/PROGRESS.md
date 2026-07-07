# Documentation de progression — Vulcain

Suivi vivant du développement (exigé par le cahier des charges, §35).

## Étape en cours
- **Phase 0 — Socle projet** : terminée.
- **Phase 1.1 — Fondation multi-tenant** : terminée.
- **Phase 1.2 — Authentification** : terminée.
- Prochaine : **Phase 1.3 — RBAC dynamique + espace super-admin plateforme**.

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
