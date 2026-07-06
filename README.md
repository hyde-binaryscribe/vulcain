# Vulcain

Application web d'**inventaire pour sapeurs-pompiers**, en **modèle SaaS multi-locataire
(multi-tenant) distribuable**. Une seule installation héberge plusieurs organisations
clientes (centres de secours / SDIS) avec des données strictement cloisonnées, tout en
restant déployable en **mono-tenant self-host** via Docker.

> Stack : PHP 8.3 · Laravel 13 · Inertia + Vue 3 · Tailwind CSS · PostgreSQL 16 · Redis.
> Voir `docs/architecture.md` pour l'analyse et les décisions techniques.

---

## Démarrage rapide (environnement de test local, Docker)

Prérequis : **Docker** et **Docker Compose**.

```bash
# 1. Configuration
cp .env.example .env

# 2. Construction et démarrage des conteneurs
docker compose up -d --build

# 3. Dépendances (dans le conteneur app)
docker compose exec app composer install
docker compose exec app php artisan key:generate

# 4. Base de données + données de démonstration
docker compose exec app php artisan migrate --seed

# 5. Front (le service `vite` compile automatiquement en dev)
```

L'application est disponible sur **http://localhost:8080**.

### Créer le premier administrateur plateforme (aucun mot de passe par défaut)

```bash
docker compose exec app php artisan vulcain:create-platform-admin
```

Le mot de passe est saisi de façon masquée (jamais dans le code, les logs ou l'historique
du terminal).

---

## Développement sans Docker

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# Configurer la connexion PostgreSQL dans .env
php artisan migrate --seed
npm run dev            # serveur Vite (HMR)
php artisan serve      # http://localhost:8000
```

---

## Tests

```bash
php artisan test          # tests Feature + Unit
vendor/bin/pint --test    # style de code (PSR-12)
```

---

## Documentation

| Document | Contenu |
|---|---|
| `docs/architecture.md` | Analyse, architecture multi-tenant, stratégies techniques, plan |
| `docs/PROGRESS.md`     | Suivi vivant du développement |
| `docs/security.md`     | Mesures de sécurité (créé au fil du développement) |
| `docs/database.md`     | Modèle de données (créé au fil du développement) |

---

## Multi-tenant en bref

- **Isolation** : chaque table métier porte `organisation_id` ; un *global scope* filtre
  automatiquement toutes les requêtes sur l'organisation courante.
- **Résolution du tenant** : par sous-domaine (`caserne.vulcain.app`) ou via l'organisation
  de l'utilisateur authentifié.
- **Espace plateforme** : un super-admin gère les organisations, les plans et les quotas,
  strictement cloisonné du métier de chaque tenant.

## Sécurité (extraits)

Argon2id · CSRF · protection XSS · CSP · requêtes préparées (ORM) · cookies
Secure/HttpOnly/SameSite · rotation de session · rate limiting · contrôle des permissions
côté serveur · anti-IDOR · fichiers privés hors webroot servis par URL signée · journal
d'audit append-only · secrets hors dépôt Git.
