# Déploiement sur Plesk

Guide de mise en production de **Vulkain** (Laravel 13 + Inertia/Vue 3 + Tailwind,
SaaS multi-tenant **par compte**) sur un hébergement **Plesk** (Obsidian,
Apache+nginx) **sans accès SSH** (client mutualisé).

> ℹ️ Modèle de tenant retenu : **un seul hôte applicatif** `app.vulkain.eu`.
> L'organisation n'est **plus** déduite d'un sous-domaine (`caserne.app…`) mais
> du **compte connecté** : chaque e-mail est unique au niveau global et rattaché
> à une organisation (1 compte = 1 organisation). Conséquence directe : **aucun
> sous-domaine générique**, **aucun certificat SSL wildcard**, **aucune
> validation DNS-01** — uniquement des certificats standard Let's Encrypt
> (HTTP-01) sur les 4 hôtes fixes. Voir §3 et §7.

Dans tout ce guide, remplace `vulkain.eu` par ton domaine réel si besoin.

---

## 1. Prérequis serveur

Composants à activer côté Plesk (**client simple**, sans droits d'installation
d'extensions serveur) :

- **PHP 8.3** (Plesk > *Paramètres PHP* du domaine), avec les extensions :
  `pdo_mysql` (ou `pdo_pgsql`), `mbstring`, `openssl`, `ctype`, `tokenizer`,
  `xml`, `curl`, `fileinfo`, `bcmath`, `gd`, `zip`, **`sodium`** (indispensable
  pour le hachage **Argon2id** des mots de passe).
- **PHP Composer** (extension Plesk « PHP Composer » sur le domaine) — sert à
  installer les dépendances PHP sans SSH.
- **Laravel** (extension Plesk « Laravel ») — sert à lancer les commandes
  Artisan (`migrate`, `optimize`, création d'admin) sans SSH.
- **Tâches planifiées** (Plesk > *Sites & domaines > Tâches planifiées*) —
  disponibles sur cette offre, utiles pour la file d'attente (§9).
- **MySQL / MariaDB** (base fournie par l'offre mutualisée). PostgreSQL
  fonctionne aussi si disponible (voir §4).

> ⚠️ **Pas de SSH, pas de Node.js sur le serveur.** Ce guide n'utilise donc que
> les extensions Plesk (PHP Composer + Laravel) pour les commandes, et les
> assets front (`public/build`) sont **versionnés dans Git** — rien à compiler
> côté serveur (voir §5).
>
> ⚠️ **Pas de Redis** en mutualisé : cache et file d'attente passent sur la
> **base de données** / synchrone (voir §6). Un `.env` qui pointe vers Redis
> provoque une erreur 500 (`getaddrinfo for redis failed`).

---

## 2. Récupérer le code (Git)

**Git intégré Plesk :**
1. **Domaine > Git** > *Ajouter un dépôt*.
2. URL : `https://github.com/hyde-binaryscribe/vulcain` — branche à déployer
   (ex. `main` une fois la branche de travail fusionnée).
3. Répertoire de déploiement : **`vulkain.eu`** (dossier dédié à l'app sous la
   racine de l'abonnement — permet d'héberger plusieurs applications côte à côte).
4. Mode : *Déploiement automatique* (à chaque push) ou manuel.

> ⚠️ Les **actions de déploiement Git** de Plesk s'exécutent dans un
> environnement restreint (chroot) **sans PHP** : `php` et
> `/opt/plesk/php/8.3/bin/php` y sont introuvables. On n'y lance donc **pas**
> composer/artisan. À la place : l'extension **PHP Composer** installe les
> dépendances, et l'extension **Laravel** lance les migrations et la mise en
> cache (§7–§8). Les actions de déploiement Git restent vides (ou limitées à un
> simple `git pull`).

---

## 3. Domaines et DNS (topologie vulkain.eu)

Topologie retenue — **4 hôtes fixes**, aucun sous-domaine générique :

| Hôte | Rôle |
|---|---|
| `vulkain.eu` / `www.vulkain.eu` | Site vitrine public |
| `desk.vulkain.eu` | Desk (super-admin plateforme, garde `platform`) |
| `app.vulkain.eu` | **Application (toutes les organisations)** — login par compte |

Dans Plesk, sur **un seul abonnement**, avec **la même racine de documents**
(`vulkain.eu/public`) pour tous :

1. **Domaine principal** `vulkain.eu` (+ **`www`** en alias) → vitrine.
2. **Sous-domaine** `desk.vulkain.eu` → Desk.
3. **Sous-domaine** `app.vulkain.eu` → application.
4. **DNS** : un simple enregistrement **A** par hôte → IP du serveur (Plesk >
   *DNS*). **Pas de wildcard `*.app`** : il n'existe plus de sous-domaine par
   organisation.

> **Résolution du tenant** : le middleware `ResolveTenant` lit l'**utilisateur
> connecté** (garde `web`) et en déduit son organisation. L'hôte ne sert plus
> qu'à distinguer vitrine / application / Desk (`config/tenancy.php` :
> `vitrine_domains`, `app_domains`, `central_domains`). Toutes les organisations
> partagent l'hôte `app.vulkain.eu` ; c'est le compte qui détermine les données
> visibles.

---

## 4. Base de données

**MySQL / MariaDB (offre mutualisée)** — Plesk > *Bases de données* > *Ajouter* :
- Base : `vulcain` · Utilisateur dédié + mot de passe fort.
- Dans `.env` : `DB_CONNECTION=mysql`, `DB_PORT=3306` (§6).

> ⚠️ **MySQL/MariaDB et l'an 2038** : les colonnes `TIMESTAMP` MySQL plafonnent
> en janvier 2038. Les échéances d'abonnement (`trial_ends_at`,
> `current_period_end`) utilisent donc `DATETIME` (migration
> `subscriptions_use_datetime`) — sinon une date d'expiration lointaine lève
> `SQLSTATE[22007] Invalid datetime value`. Le schéma est déjà correct dans le
> dépôt ; assure-toi simplement que **toutes** les migrations sont jouées (§7).

**PostgreSQL (alternative)** : `DB_CONNECTION=pgsql`, `DB_PORT=5432`.

---

## 5. Assets front (Vite) — déjà compilés dans Git

Le dossier `public/build` (JS/CSS compilés) est **versionné dans le dépôt** :
il n'y a **rien à compiler sur le serveur** (pas de Node.js requis en prod).

Après une modification du front, la recompilation se fait **en local** puis est
**commitée** :
```bash
npm ci && npm run build     # génère public/build/ (manifest.json + assets)
git add public/build && git commit -m "build assets" && git push
```
Le prochain déploiement Git apporte les assets à jour automatiquement.

---

## 6. Fichier `.env` de production

Copie `.env.example` en `.env` et adapte. Modèle prêt pour Plesk mutualisé
(**MySQL, sans Redis, hôte unique**) :

```dotenv
APP_NAME=Vulkain
APP_ENV=production
APP_KEY=                      # généré à l'étape 7
APP_DEBUG=false
APP_URL=https://vulkain.eu

# Répartition des hôtes (topologie §3) — séparation stricte client / Desk
APP_CENTRAL_DOMAIN=vulkain.eu,www.vulkain.eu,desk.vulkain.eu,app.vulkain.eu
APP_VITRINE_DOMAIN=vulkain.eu,www.vulkain.eu
APP_APP_DOMAIN=app.vulkain.eu        # espace client uniquement
APP_DESK_DOMAIN=desk.vulkain.eu      # Desk management uniquement (jamais aux clients)

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr

LOG_CHANNEL=stack
LOG_LEVEL=warning

# Hachage Argon2id (nécessite l'extension sodium)
HASH_DRIVER=argon2id
ARGON_MEMORY=65536
ARGON_THREADS=4
ARGON_TIME=4

# Base MySQL/MariaDB de l'offre mutualisée
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vulcain
DB_USERNAME=vulcain
DB_PASSWORD=********

# Sessions en base (révocation des sessions actives). Le point initial permet
# de partager la session entre desk.vulkain.eu et app.vulkain.eu : c'est ce qui
# rend possible « Se connecter en tant qu'admin » depuis le Desk (la connexion
# posée sur le Desk est reconnue à l'arrivée sur l'application).
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=.vulkain.eu
SESSION_SECURE_COOKIE=true        # HTTPS obligatoire en prod
SESSION_SAME_SITE=lax

# Pas de Redis : tout passe en base / synchrone
CACHE_STORE=database
QUEUE_CONNECTION=sync             # notifications envoyées dans la requête
BROADCAST_CONNECTION=log

FILESYSTEM_DISK=local

# E-mail — invitations, réinitialisation de mot de passe, décisions de congés.
# Boîte du domaine hébergée sur le serveur mail Plesk (voir §6bis).
MAIL_MAILER=smtp
MAIL_HOST=mail.vulkain.eu          # nom d'hôte du serveur mail (cert TLS valide)
MAIL_PORT=587                      # STARTTLS ; ou 465 avec MAIL_SCHEME=smtps
MAIL_SCHEME=smtp                   # smtp = STARTTLS (587) ; smtps = TLS implicite (465)
MAIL_USERNAME=no-reply@vulkain.eu
MAIL_PASSWORD=********              # mot de passe de la boîte créée dans Plesk
MAIL_FROM_ADDRESS="no-reply@vulkain.eu"
MAIL_FROM_NAME="Vulkain"
```

> **Sessions** : `SESSION_DOMAIN=.vulkain.eu` (avec le point initial) partage la
> session entre `desk.vulkain.eu` et `app.vulkain.eu`. C'est nécessaire à
> l'action Desk « Se connecter en tant qu'admin » (impersonation), qui pose la
> connexion sur le Desk et la retrouve sur l'application.

> **File d'attente** : `QUEUE_CONNECTION=sync` suffit pour démarrer (les
> notifications partent pendant la requête). Pour découpler, passe à `database`
> et ajoute une tâche planifiée Plesk (§9).

---

## 6bis. E-mail (boîte Plesk du domaine)

Vulkain envoie des e-mails transactionnels : invitations d'administrateurs,
réinitialisation de mot de passe, décisions de congés. On utilise une boîte du
domaine hébergée par le serveur mail de Plesk — sans compte tiers.

### a. Créer la boîte d'envoi
*Domaine `vulkain.eu` > Messagerie > Adresses e-mail > Créer une adresse* :
- Adresse : **`no-reply@vulkain.eu`**
- Mot de passe : fort, reporté dans `MAIL_PASSWORD` du `.env` (jamais commité).
- Laisser la boîte activée (elle doit pouvoir s'authentifier en SMTP).

### b. Renseigner le `.env`
Reprendre le bloc e-mail du `.env` (§6). Le point sensible est **`MAIL_HOST`** :
- Utiliser le **nom d'hôte du serveur mail** dont le certificat TLS est valide —
  en général `mail.vulkain.eu` (créé par Plesk) ou le FQDN du serveur. Éviter
  `localhost` : le certificat ne correspondrait pas et STARTTLS échouerait.
- `MAIL_PORT=587` + `MAIL_SCHEME=smtp` (STARTTLS), ou `465` + `MAIL_SCHEME=smtps`.

### c. Délivrabilité — SPF / DKIM / DMARC (indispensable)
Sans ces enregistrements, les messages partent en indésirables ou sont rejetés.
Dans *Domaine > Messagerie > Paramètres de messagerie* (ou *Outils & Paramètres >
Serveur de messagerie*) :
- **SPF** : activer la signature SPF (Plesk publie l'enregistrement TXT).
- **DKIM** : activer la signature DKIM (Plesk crée la clé et l'enregistrement).
- **DMARC** : activer la politique DMARC.

Puis vérifier dans la **zone DNS** du domaine que les TXT correspondants sont
bien publiés (si le DNS est géré ailleurs qu'à Plesk, recopier ces
enregistrements chez le registrar).

### d. Tester sans SSH
Depuis l'extension **Laravel** de Plesk (§7), lancer :
```
vulcain:mail-test votre.adresse@exemple.fr
```
La commande affiche le transport utilisé (mailer, hôte, expéditeur), envoie un
message de test et **remonte l'erreur exacte** en cas d'échec (authentification,
TLS, hôte injoignable). Contrôler la réception (et les indésirables).

> Tant que `MAIL_MAILER=log`, aucun e-mail n'est réellement envoyé : les messages
> sont écrits dans `storage/logs`. Passer à `MAIL_MAILER=smtp` pour l'envoi réel.

---

## 7. Racine de documents, HTTPS et initialisation

### Racine de documents
Le point d'entrée web de Laravel est **`public/`**. Dans Plesk :
*Domaine > Hébergement & DNS > Racine des documents* → `vulkain.eu/public`
(à faire **aussi** pour `desk.vulkain.eu` et `app.vulkain.eu`).

### Certificats SSL (obligatoire)
- Extension **SSL It!** > *Let's Encrypt*, un **certificat standard** (validation
  **HTTP-01** automatique) pour chaque hôte : `vulkain.eu`, `www.vulkain.eu`,
  `desk.vulkain.eu`, `app.vulkain.eu`.
- **Aucun wildcard, aucune validation DNS-01** : la topologie hôte unique n'en a
  plus besoin.
- Active la **redirection HTTP → HTTPS**.

### Initialisation applicative (sans SSH)
1. **Dépendances PHP** — extension **PHP Composer** sur le domaine : bouton
   *Installer* (équivaut à `composer install --no-dev --optimize-autoloader`).
   C'est ce qui crée `vendor/autoload.php` (sans ça : 500 « vendor/autoload.php
   No such file »).
2. **Commandes Artisan** — extension **Laravel** sur le domaine (elle exécute
   Artisan avec le bon binaire `/opt/plesk/php/8.3/bin/php`) :
   ```
   key:generate --force        # renseigne APP_KEY (une seule fois)
   migrate --force             # crée / met à jour le schéma
   storage:link                # lien public/storage
   optimize:clear              # purge config + routes + vues en cache
   ```

> ⚠️ **Ne pas exécuter `php artisan optimize` (ni `route:cache` / `config:cache`)
> tant que les déploiements sont manuels.** Ces commandes figent la config **et
> les routes** dans un fichier de cache. Si un déploiement ultérieur ajoute une
> route (ex. gestion des abonnements) ou change le `.env` sans que le cache soit
> repurgé, la prod continue d'utiliser l'ancien cache → **404 sur les nouvelles
> routes** ou **500 sur une config périmée**. La commande de référence après
> chaque déploiement est donc **`optimize:clear`**, pas `optimize`. Le gain de
> perf du cache est négligeable à cette échelle et ne vaut pas le risque.

### Premier compte
Via l'extension **Laravel**, lance la commande de création d'admin plateforme.
⚠️ Le champ de l'extension **coupe l'argument sur l'espace** : n'utilise donc
**pas** d'espace dans `--name`, et passe le mot de passe en non-interactif :
```
vulcain:create-platform-admin --name=Alexandre --email=... --password=...
```
Puis provisionne une organisation depuis le Desk (`desk.vulkain.eu`).

> N'utilise **pas** `--seed` en production (le seed crée des données de démo).

---

## 8. Résumé du cycle de déploiement (sans SSH)

À chaque mise à jour du code :

1. **Git** (Plesk) apporte le nouveau code (dont `public/build` déjà compilé).
2. **PHP Composer** (extension) : *Installer* si `composer.lock` a changé.
3. **Laravel** (extension) : `migrate --force`, puis **`optimize:clear`**.

> Les *actions de déploiement Git* restent vides : leur chroot n'a pas de PHP
> (voir §2). Tout ce qui touche PHP passe par les deux extensions ci-dessus.

> ⚠️ **`optimize:clear` est obligatoire après chaque déploiement.** Un cache de
> routes/config périmé est la cause n°1 des « 404 sur une page qui existe » et
> des « 500 après changement de `.env` ». Ne pas relancer `optimize` (voir §7).

---

## 9. (Optionnel) File d'attente découplée & planificateur

Avec `QUEUE_CONNECTION=sync`, **aucun cron n'est requis** (les notifications
partent dans la requête).

Si tu passes la file en `database` (`QUEUE_CONNECTION=database` +
`queue:table` puis `migrate --force`), ajoute une **tâche planifiée** Plesk
(*Sites & domaines > Tâches planifiées*), toutes les minutes, avec le binaire
PHP Plesk :
```
/opt/plesk/php/8.3/bin/php ~/vulkain.eu/artisan queue:work --stop-when-empty --max-time=55
```
(Le mutualisé n'a pas Supervisor ; ce cron « une salve par minute » le remplace.)

---

## 9bis. Planificateur des échéances (événements automatiques) — requis

Les **événements d'échéance** (entretien, désinfection, péremptions de lots,
expiration des documents) sont générés par une commande planifiée. Sans cette
tâche, ils **ne se créent pas** automatiquement (le reste de l'application
fonctionne, mais le fil « Événements » et les alertes d'échéance restent vides).

**Où :** Plesk > *Sites & domaines* > **Tâches planifiées** > *Ajouter une tâche*.

> ⚠️ **Hébergement sans PHP CLI (shell chrooté, ex. brocloud) :** si une tâche
> « Exécuter une commande » avec `/opt/plesk/php/8.x/bin/php` renvoie
> *« No such file or directory »*, c'est que le cron n'a pas accès à PHP en
> ligne de commande. Utilise alors l'**Option C (déclencheur par URL)** ci-dessous,
> qui ne dépend pas du PHP CLI.

### Option A — Planificateur Laravel (si le PHP CLI est accessible)

Une seule tâche qui tourne **chaque minute** ; Laravel décide quand exécuter
(la génération est calée à 06:30). C'est le mécanisme standard et il servira
aussi à d'éventuelles futures tâches planifiées, sans retoucher le Cron.

- **Type :** *Lancer une commande*
- **Commande :**
  ```
  /opt/plesk/php/8.3/bin/php ~/vulkain.eu/artisan schedule:run >/dev/null 2>&1
  ```
- **Fréquence :** chaque minute — expression cron `* * * * *`
  (dans Plesk : cocher *Cron style*, ou choisir « Chaque minute »).

> Adapter le chemin `~/vulkain.eu/artisan` à la racine réelle du site (le même
> que celui utilisé pour `migrate` / `optimize:clear`), et la version PHP
> (`8.3`) à celle du domaine.

### Option B — Une seule tâche quotidienne (plus simple)

Si tu préfères éviter une tâche « chaque minute », appelle directement la
commande **une fois par jour** :

- **Commande :**
  ```
  /opt/plesk/php/8.3/bin/php ~/vulkain.eu/artisan vulcain:generate-echeance-events >/dev/null 2>&1
  ```
- **Fréquence :** une fois par jour, p. ex. `35 6 * * *` (06 h 35).

### Option C — Déclencheur par URL (recommandé sans PHP CLI)

Fonctionne même quand le cron n'a pas de PHP (shell chrooté). L'application
expose un endpoint protégé par un **jeton secret** ; Plesk l'appelle en HTTP.

1. **Générer un jeton** (chaîne aléatoire longue) et l'ajouter au `.env` :
   ```
   CRON_TOKEN=colle-ici-une-longue-chaine-aleatoire
   ```
   puis, via l'extension Laravel : `optimize:clear` (recharge la config).
2. **Créer la tâche planifiée** de type **« Récupérer une URL »** (*Fetch a URL*) :
   ```
   https://app.vulkain.eu/cron/echeances?token=LE_MEME_JETON
   ```
3. **Fréquence :** une fois par jour, p. ex. `35 6 * * *` (06 h 35).

L'endpoint renvoie `OK — X créé(s), Y clôturé(s).` en cas de succès, `404` si le
jeton est absent/incorrect (il est désactivé tant que `CRON_TOKEN` n'est pas défini).
Test manuel : ouvrir l'URL dans le navigateur.

### Vérifier

- Lancer la commande à la main depuis l'extension **Laravel** (ou en tâche
  ponctuelle) : `vulcain:generate-echeance-events` doit afficher, par
  organisation, « X créé(s), Y clôturé(s) ». Elle est **idempotente** (aucun
  doublon si relancée).

---

## 10. Vérifications post-déploiement

- [ ] `https://vulkain.eu` affiche la vitrine (HTTPS vert).
- [ ] `https://app.vulkain.eu/login` : connexion par e-mail + mot de passe, et
      arrivée sur le tableau de bord de la bonne organisation.
- [ ] `https://app.vulkain.eu/inscription` permet de créer une organisation
      (auto-inscription + essai).
- [ ] Un compte dont l'organisation est **suspendue** ne peut pas se connecter.
- [ ] `https://desk.vulkain.eu` : accès Desk réservé à la garde `platform`.
- [ ] `vulcain:mail-test <adresse>` réussit et l'e-mail est reçu (SMTP + SPF/DKIM OK).
- [ ] Création d'un utilisateur → e-mail d'invitation reçu.
- [ ] `APP_DEBUG=false` (aucune stacktrace publique).
- [ ] `storage/logs/laravel.log` sans erreur ; droits d'écriture OK.
- [ ] Un protocole se lance et se valide (base + sessions OK).
- [ ] Tâche planifiée active (§9bis) : `vulcain:generate-echeance-events`
      s'exécute et le fil « Événements » se remplit selon les échéances.

---

## 11. Rappels de sécurité (prod)

- `APP_DEBUG=false`, `APP_ENV=production`.
- `SESSION_SECURE_COOKIE=true` et `SESSION_ENCRYPT=true` (déjà activés ci-dessus).
- Mots de passe DB/SMTP forts, jamais commités (`.env` hors Git — déjà `.gitignore`).
- Sauvegardes Plesk planifiées (base + `storage/app`).
- Voir `docs/security.md` pour le durcissement applicatif déjà en place.

---

## 12. Domaine personnalisé (évolution future)

Le modèle hôte unique par compte n'a pas besoin de domaine par organisation.
Si une formule premium devait un jour offrir un domaine dédié (ex.
`inventaire.sdis63.fr`), la résolution par compte reste inchangée ; il suffirait
d'ajouter côté Plesk un **alias de domaine** (même racine de documents) et un
certificat Let's Encrypt HTTP-01. Aucune infrastructure wildcard n'est requise.
Fonctionnalité **non nécessaire** au fonctionnement actuel.
