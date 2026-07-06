# Documentation de progression — Vulcain

Suivi vivant du développement (exigé par le cahier des charges, §35).

## Étape en cours
- **Phase 0 — Socle projet** : terminée.
- Prochaine : **Phase 1.1 — fondation multi-tenant**.

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
