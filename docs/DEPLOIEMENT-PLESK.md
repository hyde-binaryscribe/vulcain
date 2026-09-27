# Déploiement sur Plesk

Guide de mise en production de **Vulcain** (Laravel 13 + Inertia/Vue 3 + Tailwind,
SaaS multi-tenant par sous-domaine) sur un serveur **Plesk** (Obsidian, Apache+nginx).

> ⚠️ Point critique propre à ce projet : l'application est **multi-tenant par
> sous-domaine** (`caserne.mondomaine.fr` → organisation « caserne », le Desk
> plateforme vivant sur le domaine racine). Il faut donc un **sous-domaine
> générique** `*.mondomaine.fr` + un **certificat SSL wildcard** + un **cookie de
> session partagé** entre le domaine et ses sous-domaines. C'est ce que la plupart
> des tutos Plaesk génériques oublient — voir §3 et §7.

Dans tout ce guide, remplace `mondomaine.fr` par ton domaine réel.

---

## 1. Prérequis serveur

Composants à activer dans **Plesk > Outils & Paramètres** :

- **PHP 8.3** (Plesk > Paramètres PHP du domaine), avec les extensions :
  `pdo_pgsql` (ou `pdo_mysql`), `mbstring`, `openssl`, `ctype`, `tokenizer`,
  `xml`, `curl`, `fileinfo`, `bcmath`, `gd`, `zip`, **`sodium`** (indispensable
  pour le hachage **Argon2id** des mots de passe).
- **PostgreSQL** (recommandé, identique au dev) — composant à installer via
  *Installeur de composants* si absent. MySQL/MariaDB fonctionne aussi (voir §4).
- **Composer** (extension Plesk « PHP Composer » ou binaire via SSH).
- **Node.js** (extension « Node.js » de Plesk) pour compiler les assets Vite —
  *ou* compilation en local puis upload (voir §5).
- **Accès SSH** vivement conseillé (Artisan, migrations, cache).
- **Redis** : le plus souvent **absent** en hébergement Plesk mutualisé. Ce guide
  bascule cache / file d'attente sur la **base de données** (voir §6). Si tu as un
  Redis + l'extension `phpredis`, tu peux garder la config Redis d'origine.

---

## 2. Récupérer le code (Git)

Deux options.

### Option A — Git intégré Plesk (recommandé)
1. **Domaine > Git** > *Ajouter un dépôt*.
2. URL : `https://github.com/hyde-binaryscribe/vulcain` — branche à déployer
   (ex. `main` une fois la branche de travail fusionnée).
3. Répertoire de déploiement : **`vulkain.eu`** (dossier dédié à l'app sous la
   racine de l'abonnement — permet d'héberger plusieurs applications côte à côte).
4. Mode : *Déploiement automatique* (à chaque push) ou manuel.
5. Renseigner les **actions de déploiement** (§8) qui lanceront composer, les
   migrations et la mise en cache après chaque pull.

### Option B — SSH manuel
```bash
cd ~                      # racine de l'abonnement (où cohabitent tes apps)
git clone https://github.com/hyde-binaryscribe/vulcain vulkain.eu
```

---

## 3. Domaines, sous-domaine générique et DNS (topologie vulkain.eu)

Le cœur du multi-tenant. Topologie retenue en production :

| Hôte | Rôle |
|---|---|
| `vulkain.eu` / `www.vulkain.eu` | Site vitrine public |
| `desk.vulkain.eu` | Desk (super-admin) |
| `app.vulkain.eu` | Entrée application (inscription / recherche d'organisation) |
| `<slug>.app.vulkain.eu` | Une organisation cliente (formule de base) |
| domaine perso du client | Une organisation cliente (formule supérieure) — voir §12 |

Dans Plesk, sur **un seul abonnement**, avec **la même racine de documents**
(`vulkain.eu/public`) pour tous :

1. **Domaine principal** `vulkain.eu` (+ **`www`** en alias) → vitrine.
2. **Sous-domaine** `desk.vulkain.eu` → Desk.
3. **Sous-domaine** `app.vulkain.eu` → entrée application.
4. **Sous-domaine générique** `*.app.vulkain.eu` : Plesk > *Sous-domaines* >
   *Ajouter*, nom **`*.app`**. C'est lui qui sert **toutes les organisations**.
5. **DNS** : enregistrement **A wildcard** `*.app.vulkain.eu` → IP du serveur
   (Plesk > *DNS*). Sans ça, `caserne.app.vulkain.eu` ne résout pas.

> Résolution : `config/tenancy.php` lit `APP_CENTRAL_DOMAIN` (les 4 hôtes
> ci-dessus) ; tout `<slug>.app.vulkain.eu` hors de cette liste devient le slug
> de l'organisation. Le middleware teste le domaine central **le plus long
> d'abord**, donc `caserne.app.vulkain.eu` donne bien le slug `caserne`.


> Résolution du tenant : `config/tenancy.php` lit `APP_CENTRAL_DOMAIN`. Tout
> sous-domaine **hors** de cette liste est traité comme le slug d'une organisation.

---

## 4. Base de données

**PostgreSQL (recommandé)** — Plesk > *Bases de données* > *Ajouter* :
- Base : `vulcain` · Utilisateur dédié + mot de passe fort.
- Renseigne `DB_*` dans `.env` (§6).

**MySQL/MariaDB (alternative)** : crée la base, puis dans `.env`
`DB_CONNECTION=mysql`, `DB_PORT=3306`. Le schéma est standard Laravel et portable ;
teste les migrations sur une base vierge avant la prod.

---

## 5. Compiler les assets front (Vite)

Le dossier `public/build` (JS/CSS compilés) n'est **pas** versionné : il faut le
générer.

**Via Node.js Plesk** : *Domaine > Node.js*, puis exécute :
```bash
npm ci
npm run build
```
(le résultat atterrit dans `public/build/manifest.json` + assets).

**Ou en local puis upload** :
```bash
npm ci && npm run build
# puis upload du dossier public/build/ vers vulkain.eu/public/build/
```

---

## 6. Fichier `.env` de production

Copie `.env.example` en `.env` et adapte. Modèle prêt pour Plesk **sans Redis** :

```dotenv
APP_NAME=Vulcain
APP_ENV=production
APP_KEY=                      # généré à l'étape 7
APP_DEBUG=false
APP_URL=https://vulkain.eu

# Répartition des hôtes (topologie §3)
APP_CENTRAL_DOMAIN=vulkain.eu,www.vulkain.eu,desk.vulkain.eu,app.vulkain.eu
APP_VITRINE_DOMAIN=vulkain.eu,www.vulkain.eu
APP_APP_DOMAIN=app.vulkain.eu

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr

LOG_CHANNEL=stack
LOG_LEVEL=warning

# Hachage Argon2id (nécessite l'extension sodium)
HASH_DRIVER=argon2id
ARGON_MEMORY=65536
ARGON_THREADS=4
ARGON_TIME=4

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vulcain
DB_USERNAME=vulcain
DB_PASSWORD=********

# Sessions en base (révocation des sessions actives) + cookie partagé entre
# le domaine et TOUS ses sous-domaines (indispensable au multi-tenant)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=.vulkain.eu        # le point initial = valable sur *.vulkain.eu
SESSION_SECURE_COOKIE=true        # HTTPS obligatoire en prod
SESSION_SAME_SITE=lax

# Pas de Redis : tout passe en base / synchrone
CACHE_STORE=database
QUEUE_CONNECTION=sync             # notifications envoyées dans la requête
BROADCAST_CONNECTION=log

FILESYSTEM_DISK=local

# E-mail (invitations, réinitialisation) — renseigne un vrai SMTP
MAIL_MAILER=smtp
MAIL_HOST=smtp.vulkain.eu
MAIL_PORT=587
MAIL_USERNAME=no-reply@vulkain.eu
MAIL_PASSWORD=********
MAIL_FROM_ADDRESS="no-reply@vulkain.eu"
MAIL_FROM_NAME="Vulcain"
```

> **Cookie de session** : `SESSION_DOMAIN=.vulkain.eu` (avec le point) est ce
> qui permet de rester connecté en passant de l'inscription (`app.vulkain.eu`)
> à son espace `caserne.app.vulkain.eu`.
> Sans ça, chaque sous-domaine redemande une connexion.

> **File d'attente** : `QUEUE_CONNECTION=sync` suffit pour démarrer (les
> notifications partent pendant la requête). Pour découpler, passe à `database`
> et ajoute une tâche planifiée Plesk (§9).

---

## 7. Racine de documents, HTTPS et initialisation

### Racine de documents
Le point d'entrée web de Laravel est **`public/`**. Dans Plesk :
*Domaine > Hébergement & DNS > Racine des documents* → `vulkain.eu/public`
(à faire **aussi** pour `desk`, `app` et le sous-domaine générique `*.app`).

### Certificats SSL (obligatoire)
- Extension **SSL It!** > *Let's Encrypt* :
  - certificat standard pour `vulkain.eu`, `www.vulkain.eu`, `desk.vulkain.eu`,
    `app.vulkain.eu` (validation HTTP-01 automatique) ;
  - certificat **wildcard `*.app.vulkain.eu`** pour les organisations — le
    wildcard exige une **validation DNS-01** (Plesk affiche un TXT à ajouter,
    automatique si ton DNS est géré par Plesk).
- Active la **redirection HTTP → HTTPS**.

### Initialisation applicative (SSH, depuis `vulkain.eu`)
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force        # renseigne APP_KEY
php artisan migrate --force             # crée le schéma
php artisan storage:link                # lien public/storage
php artisan optimize                    # cache config + routes + vues + events
```
Droits d'écriture (utilisateur PHP du domaine) :
```bash
chmod -R ug+rwX storage bootstrap/cache
```

### Premier compte
```bash
# Administrateur de la plateforme (Desk, sur le domaine racine)
php artisan vulcain:create-platform-admin

# Puis provisionne une organisation depuis le Desk, ou en CLI :
php artisan vulcain:create-user --organisation=<slug> --email=... --role=administrateur
```
> N'utilise **pas** `--seed` en production (le seed crée des données de démo).

---

## 8. Actions de déploiement Plesk (Git)

Dans *Domaine > Git > (ton dépôt) > Actions de déploiement supplémentaires*,
colle ceci — rejoué à chaque pull :

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
# Si Node.js est configuré sur le domaine :
# npm ci && npm run build
```

> Si tu compiles les assets en local, retire la ligne `npm` et upload
> `public/build` séparément après chaque changement de front.

---

## 9. (Optionnel) File d'attente découplée & planificateur

Le projet **n'a aucune tâche planifiée** définie : aucun cron n'est requis avec
`QUEUE_CONNECTION=sync`.

Si tu passes la file en `database` (`QUEUE_CONNECTION=database` +
`php artisan queue:table && php artisan migrate --force`), ajoute une **tâche
planifiée** Plesk (*Outils & Paramètres > Tâches planifiées*), toutes les minutes :
```bash
cd ~/vulkain.eu && php artisan queue:work --stop-when-empty --max-time=55
```
(Plesk mutualisé n'a pas Supervisor ; ce cron « une salve par minute » le remplace.)

---

## 10. Vérifications post-déploiement

- [ ] `https://vulkain.eu` affiche la vitrine (HTTPS vert).
- [ ] `https://app.vulkain.eu/inscription` permet de créer une organisation.
- [ ] `https://<slug>.app.vulkain.eu` charge l'organisation (wildcard DNS + SSL OK).
- [ ] Connexion, puis navigation Desk ↔ sous-domaine **sans reconnexion**
      (cookie `SESSION_DOMAIN` OK).
- [ ] Création d'un utilisateur → e-mail d'invitation reçu (SMTP OK).
- [ ] `APP_DEBUG=false` (aucune stacktrace publique).
- [ ] `storage/logs/laravel.log` sans erreur ; droits d'écriture OK.
- [ ] Un protocole se lance et se valide (base + sessions OK).

---

## 11. Rappels de sécurité (prod)

- `APP_DEBUG=false`, `APP_ENV=production`.
- `SESSION_SECURE_COOKIE=true` et `SESSION_ENCRYPT=true` (déjà activés ci-dessus).
- Mots de passe DB/SMTP forts, jamais commités (`.env` hors Git — déjà `.gitignore`).
- Sauvegardes Plesk planifiées (base + `storage/app`).
- Voir `docs/security.md` pour le durcissement applicatif déjà en place.

---

## 12. Domaine personnalisé (formule supérieure)

Un client premium peut utiliser son propre domaine (ex. `inventaire.sdis63.fr`)
au lieu de `<slug>.app.vulkain.eu`. Prévu côté applicatif au **lot V4**
(champ `custom_domain` sur l'organisation + résolution par hôte). Côté Plesk,
par client, en manuel (volume faible) :

1. **Le client** crée un enregistrement DNS `A` (ou `CNAME`) de son domaine vers
   l'IP du serveur.
2. **Toi dans Plesk** : sur l'abonnement `vulkain.eu`, *Sites & domaines >
   Ajouter un alias de domaine* → le domaine du client, **même racine de
   documents**.
3. **SSL** : *SSL It!* émet un Let's Encrypt (HTTP-01) pour ce domaine une fois
   le DNS pointé.
4. L'application reconnaît l'hôte via `custom_domain` et charge la bonne
   organisation.

> Automatisable plus tard via l'API Plesk (`plesk bin domalias` / XML-RPC) si le
> nombre de clients premium le justifie.

