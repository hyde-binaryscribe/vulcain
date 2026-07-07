# Vulcain

Application web d'**inventaire pour sapeurs-pompiers**, en **modèle SaaS multi-locataire
(multi-tenant) distribuable**. Une seule installation héberge plusieurs organisations
clientes (centres de secours / SDIS) avec des données strictement cloisonnées, tout en
restant déployable en **mono-tenant self-host** via Docker.

> Stack : PHP 8.3 · Laravel 13 · Inertia + Vue 3 · Tailwind CSS · PostgreSQL 16 · Redis.
> Voir `docs/architecture.md` pour l'analyse et les décisions techniques.

---

## Démarrage rapide (environnement de test local, Docker)

Prérequis : **Docker Desktop** (avec le moteur démarré). Une seule commande — le conteneur
`app` prépare tout automatiquement (`.env`, dépendances, clé, migrations, données de démo).

```bash
docker compose up --build
```

Attends dans les logs la ligne :

```
✅ Vulcain est prêt : http://demo.localhost:8080
```

puis ouvre un **sous-domaine d'organisation** dans le navigateur :

| URL | Espace |
|---|---|
| http://demo.localhost:8080 | Organisation de démo « CIS Démonstration » |
| http://caserne-nord.localhost:8080 | Organisation de démo « CIS Nord » |
| http://localhost:8080 | Domaine central (espace plateforme) |

> Les navigateurs (Edge, Chrome, Firefox) résolvent automatiquement `*.localhost` vers
> `127.0.0.1` : aucune configuration de `hosts` n'est nécessaire.

Pour lancer en arrière-plan : `docker compose up -d --build` (puis `docker compose logs -f app`).
Pour tout arrêter : `docker compose down` (ajouter `-v` pour effacer aussi la base).

### Windows 11 (Docker Desktop)

- Installe **Docker Desktop** avec le backend **WSL2** (proposé par défaut) et laisse-le démarrer.
- Ouvre **PowerShell** ou **Git Bash** dans le dossier du projet et lance `docker compose up --build`.
- La première construction télécharge les images (quelques minutes) ; les suivantes sont quasi
  instantanées.
- Aucune conversion de fins de ligne à craindre : le dépôt force `LF` (`.gitattributes`).

### Créer un compte pour se connecter (aucun mot de passe par défaut)

La commande crée un utilisateur dans une organisation, mot de passe saisi de façon **masquée** :

```bash
docker compose exec app php artisan vulcain:create-user --organisation=demo --role=administrateur
```

Renseigne prénom, nom, e-mail, puis le mot de passe (min. 10 caractères, lettres + chiffres).
Rôles disponibles : `administrateur`, `responsable_pharmacie`, `verificateur`.

Pour attribuer un rôle à un **compte existant** :

```bash
docker compose exec app php artisan vulcain:grant-role mon.email@exemple.fr --organisation=demo --role=administrateur
```

Connecte-toi ensuite sur **http://demo.localhost:8080** avec cet e-mail et ce mot de passe.

### Multi-secteurs (branding par organisation)

Les organisations de démonstration illustrent les 3 secteurs (chacune sur son sous-domaine,
avec sa couleur d'accent) :

| Sous-domaine | Secteur | Accent |
|---|---|---|
| http://demo.localhost:8080 | Sapeurs-pompiers | rouge |
| http://ambulance-sud.localhost:8080 | Ambulance privée | bleu |
| http://protection-civile.localhost:8080 | Sécurité civile | orange |

> **Mot de passe oublié** en local : l'e-mail part vers les logs (`MAIL_MAILER=log`). Récupère
> le lien de réinitialisation avec `docker compose logs app | grep reset-password`.

> Les rôles (Administrateur / Responsable pharmacie / Vérificateur) et l'espace exploitant
> arrivent en Phase 1.3.

---

### Rechargement à chaud du front (optionnel)

Le service `assets` construit le front une fois au démarrage — suffisant pour tester
l'application. Pour du rechargement à chaud pendant le développement de l'interface, utilise
le flux **sans Docker** ci-dessous (`npm run dev` sur la machine hôte).

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
