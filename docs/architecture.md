# Vulcain — Architecture & analyse (Mission 1)

> Application d'inventaire pour sapeurs-pompiers, **adaptée en modèle SaaS distribuable**.
> Ce document est le livrable de la « Première mission » du cahier des charges : analyse,
> architecture proposée, décisions techniques, risques et plan de développement.
> **Aucun module n'est encore développé, aucune table n'est encore créée.**

---

## 0. Ce que change « SaaS distribuable » par rapport au cahier initial

Le cahier des charges décrit une application mono‑centre « facilement installable sur un
serveur web classique ». Le passage à un **SaaS distribuable** ne retire aucune exigence
fonctionnelle : il ajoute une **couche de multi‑location (multi‑tenant)** au‑dessus de tout
le métier existant, plus un espace d'**exploitant de la plateforme**.

| Axe | Cahier initial (mono‑centre) | Adaptation SaaS distribuable |
|---|---|---|
| Périmètre données | 1 installation = 1 caserne | 1 installation = N **organisations** clientes, données strictement cloisonnées |
| Comptes | Un admin local | Un **super‑admin plateforme** (exploitant) + des admins **par organisation** |
| Onboarding | Commande CLI 1er admin | Provisionnement d'une organisation + invitation sécurisée du 1er admin |
| Déploiement | Serveur web classique | Conteneurisé (Docker), scalable, files d'attente, stockage objet |
| Personnalisation | Logo unique | Logo / nom / thème **par organisation** (marque blanche possible) |
| Facturation | — | Plans & abonnements (prévus, activables ultérieurement) |
| Isolation fichiers | Un dossier privé | Partitionnement des fichiers **par organisation** |

Principe directeur du cahier — *« ne pas choisir une architecture inutilement complexe »* —
conservé : on retient le modèle multi‑tenant le **plus simple qui isole réellement** les
données (base partagée + discriminant `organisation_id`), et non une usine à gaz.

---

## 1. Domaines métier

1. **Plateforme (central / landlord)** — organisations, abonnements/plans, super‑admin, provisionnement, quotas.
2. **Identité & accès** — utilisateurs, authentification, sessions, RBAC dynamique, affectations véhicules.
3. **Parc & organisation physique** — véhicules, emplacements et sous‑emplacements.
4. **Catalogue matériel** — matériels, catégories, suivi unitaire/quantité, statuts.
5. **Modèles & planification d'inventaire** — modèles, versions, éléments, fréquences, échéances.
6. **Réalisation d'inventaire** — exécution tactile, autosave, brouillon, validation, verrouillage, snapshots.
7. **Contrôles photographiques** — points de contrôle configurables, comparaison dans le temps.
8. **Anomalies** — déclaration, priorités, statuts, historique complet.
9. **Réparations** — suivi, prestataires, coûts, documents, historique.
10. **Stock pharmacie & consommables** — produits, lots, mouvements, FEFO, alertes.
11. **Transversal** — historiques, journal d'audit, fichiers/documents, exports PDF, notifications, recherche globale.

---

## 2. Fonctionnalités critiques (à sécuriser en priorité)

- **Isolation multi‑tenant** : aucune fuite de données entre organisations (nouvelle exigence n°1 du SaaS).
- **Authentification & sessions** : Argon2id, réinitialisation par jeton haché à usage unique, blocage après échecs, révocation de session.
- **RBAC serveur** : toutes les permissions vérifiées côté serveur, jamais côté client seul.
- **Affectations véhicules** : un vérificateur n'accède qu'à ses véhicules autorisés (contrôle serveur + anti‑IDOR).
- **Versionnement des modèles & snapshots** : un inventaire figé n'est jamais altéré par une modification ultérieure du catalogue/modèle.
- **Autosave & conflits** : pas d'écrasement silencieux d'une version récente par une ancienne.
- **Transactions stock & FEFO** : cohérence stricte, pas de stock négatif hors dérogation tracée.
- **Verrouillage post‑validation** : correction admin uniquement, motivée, auditée.
- **Fichiers privés** : jamais accessibles via URL publique permanente.
- **Journal d'audit** : append‑only, non modifiable depuis l'interface, sans secrets.

---

## 3. Risques techniques

| Risque | Impact | Mitigation |
|---|---|---|
| Fuite inter‑tenant (oubli d'un filtre) | Critique | Scope global automatique + trait de modèle + tests anti‑fuite systématiques |
| Perte de données pendant l'inventaire (réseau tablette) | Élevé | Autosave incrémental + brouillon local (localStorage) + reprise |
| Accès concurrents à un même inventaire | Moyen | Verrouillage optimiste (jeton de version) + réponse 409 gérée |
| Corruption des stocks (races) | Élevé | Transactions + verrous de ligne (`FOR UPDATE`) + registre de mouvements |
| Croissance des fichiers/photos | Moyen | Stockage objet (S3‑compatible), miniatures, quotas par organisation |
| Génération PDF lourde bloquant les requêtes | Moyen | Files d'attente asynchrones (queue worker) |
| Dérive des performances multi‑tenant | Moyen | Index sur `organisation_id` + clés composites, pagination partout |

## 4. Risques de sécurité

- **IDOR** (accès à une ressource d'un autre utilisateur/organisation) → contrôle d'autorisation sur **chaque** ressource, jamais confiance à l'ID fourni.
- **XSS / CSRF** → encodage des sorties, protection CSRF native, CSP stricte.
- **Injection SQL** → requêtes préparées / ORM exclusivement.
- **Upload malveillant** → vérification MIME réelle, liste blanche, renommage, stockage hors webroot, pas d'exécution.
- **Vol de session** → cookies Secure/HttpOnly/SameSite, rotation d'ID après login, expiration, révocation.
- **Brute force** → limitation des tentatives + blocage temporaire + rate limiting des actions sensibles.
- **Fuite de secrets** → secrets hors dépôt Git (`.env`), jamais dans logs ni audit.
- **Escalade de privilèges** → un admin d'organisation ne doit jamais atteindre le périmètre plateforme ni une autre organisation.

---

## 5. Architecture technique proposée

### 5.1 Pile applicative (recommandation)

| Couche | Choix recommandé | Justification |
|---|---|---|
| Langage | **PHP 8.3** | Exigence du cahier, maturité, hébergement simple |
| Framework | **Laravel 11** | Écosystème SaaS complet (auth, queues, policies, migrations, tests), sécurité par défaut |
| RBAC dynamique | **spatie/laravel-permission** | Rôles & permissions en base, extensibles à chaud (exigence « ajout futur ») |
| Auth | **Laravel Fortify/Sanctum** + Argon2id | Sessions, reset, lockout, révocation, API interne |
| Frontend | **Inertia.js + Vue 3 + Tailwind CSS** | UI moderne réactive proche des maquettes, idéale pour l'inventaire tactile (autosave, progression) |
| Files d'attente | **Redis + queue worker** | Notifications, e‑mails, génération PDF asynchrone |
| Stockage fichiers | **Disque privé / objet S3‑compatible** | Fichiers privés hors webroot, URLs signées temporaires, partition par organisation |
| PDF | **dompdf / barryvdh/laravel-dompdf** | Exports serveur, gabarits par organisation |
| Base de données | **PostgreSQL** (MySQL 8 possible) | Intégrité forte, index partiels, JSONB pour snapshots |
| Conteneurisation | **Docker + docker-compose** | Distribuable, reproductible, déploiement SaaS et self‑host |

> Alternative si l'on veut rester au plus près du cahier (Bootstrap 5, pas de build JS) :
> **Laravel + Livewire + Bootstrap 5**. C'est possible, mais l'ergonomie tactile de
> l'inventaire (autosave fluide, navigation par emplacement, mode « anomalies seules »)
> est nettement meilleure avec Inertia + Vue. Recommandation forte : **Inertia + Vue**.

### 5.2 Modèle de multi‑location (multi‑tenant)

**Recommandation : base de données partagée, isolation par ligne (`organisation_id`).**

- Chaque table métier porte une colonne `organisation_id` (indexée, en clé étrangère).
- Un **global scope** Eloquent + un **trait `BelongsToOrganisation`** filtrent automatiquement toutes les requêtes sur le tenant courant.
- Le tenant courant est résolu par un **middleware** : à partir de l'organisation de l'utilisateur authentifié (et/ou d'un sous‑domaine `caserne.vulcain.app`).
- **Tables centrales (non scopées)** : `organisations`, `plans`, `subscriptions`, `platform_admins`.
- Garde‑fous : impossibilité d'écrire un enregistrement sans `organisation_id`, tests automatiques de non‑fuite inter‑tenant, blocage des requêtes hors contexte tenant.

Pourquoi ce modèle et pas *base‑par‑tenant* / *schéma‑par‑tenant* :
- **Simplicité & maintenabilité** (une seule migration, une seule sauvegarde) — conforme à « pas inutilement complexe ».
- **Coût** maîtrisé (mutualisation).
- Migration ultérieure possible vers *base‑par‑tenant* pour un très gros client exigeant une isolation physique (documenté, non construit maintenant).

### 5.3 Hiérarchie des rôles (adaptée SaaS)

```
Plateforme (central)
└── Super-admin plateforme (exploitant SaaS) — gère organisations, plans, quotas
Organisation (tenant = client : un SDIS / un centre de secours)
├── Administrateur         (accès complet interne)
├── Responsable pharmacie  (matériel, stock, inventaires, anomalies, réparations)
└── Vérificateur           (réalisation d'inventaires sur véhicules autorisés)
    └── (option activable par l'admin : voir l'historique de toute la caserne)
```

Le RBAC interne (admin / responsable pharmacie / vérificateur) reste **exactement** celui du
cahier des charges. On ajoute au‑dessus le rôle **plateforme**, cloisonné du métier.

### 5.4 Organisation du projet (arborescence cible)

```
vulcain/
├── app/
│   ├── Domain/                 # logique métier par domaine
│   │   ├── Platform/           #   organisations, plans, abonnements
│   │   ├── Identity/           #   users, roles, permissions, affectations
│   │   ├── Fleet/              #   véhicules, emplacements
│   │   ├── Catalog/            #   matériel, catégories
│   │   ├── Inventory/          #   modèles, versions, exécution, snapshots
│   │   ├── Anomaly/            #   anomalies + historique
│   │   ├── Repair/             #   réparations
│   │   ├── Pharmacy/           #   produits, lots, mouvements, FEFO
│   │   └── Shared/             #   audit, fichiers, notifications, recherche, PDF
│   ├── Http/                   # controllers, middleware (tenant, RBAC), requests
│   ├── Models/                 # modèles Eloquent (trait BelongsToOrganisation)
│   ├── Policies/               # autorisations par ressource (anti-IDOR)
│   └── Support/                # tenancy, sécurité, helpers
├── database/
│   ├── migrations/             # créées progressivement, après validation
│   └── seeders/                # données de démonstration
├── resources/js/               # Vue 3 + Inertia (UI, écran d'inventaire tactile)
├── resources/views/            # gabarits Blade (layout, PDF)
├── routes/                     # web.php, api.php, platform.php
├── tests/                      # Feature + Unit (auth, RBAC, tenant, stock, FEFO…)
├── docs/                       # architecture, sécurité, base de données, progression
├── docker/                     # images, compose
├── .env.example                # sans aucun secret
└── README.md
```

Séparation stricte demandée par le cahier : **présentation** (Vue/Blade) / **métier**
(Domain, Actions/Services) / **accès données** (Models/Repositories) / **autorisations**
(Policies, middleware) / **validation** (Form Requests) / **sécurité** (middleware, config).

---

## 6. Stratégies détaillées (points 7 à 13 de la mission)

### 6.1 Authentification & sessions
- Hachage **Argon2id** ; jamais de mot de passe en clair.
- Réinitialisation : jeton **cryptographiquement sûr, temporaire, à usage unique, stocké haché**.
- Blocage temporaire après N échecs (compteur + verrou) ; rate limiting des routes sensibles.
- Cookies **Secure / HttpOnly / SameSite** ; **rotation de l'ID de session** après connexion ; expiration ; **révocation** des sessions actives (table `sessions`).
- Profil : photo, changement de mot de passe, désactivation de compte (suppression logique).

### 6.2 RBAC & contrôle d'accès ressource
- Permissions **dynamiques en base**, vérifiées **côté serveur** systématiquement.
- **Policies Laravel** par ressource : on n'autorise jamais sur la base d'un ID fourni sans vérifier propriété **organisation + affectation véhicule**.
- Middleware d'autorisation en plus du scope tenant (défense en profondeur).

### 6.3 Fichiers privés
- Stockage **hors webroot** (disque privé ou objet S3), **partitionné par organisation**.
- Vérification **MIME réelle**, liste blanche d'extensions, taille max configurable, renommage aléatoire, anti double‑extension, strip EXIF, miniatures.
- Accès via **URL signée temporaire** + contrôle de permission à chaque service du fichier ; journalisation des suppressions ; interdiction d'exécution.

### 6.4 Inventaires : versionnement des modèles & snapshots
- Table `inventory_templates` + `inventory_template_versions` + `inventory_template_items`.
- Au **démarrage** d'un inventaire : création d'un **instantané complet** (copie figée des éléments, quantités théoriques, emplacements, points photo obligatoires) dans `inventory_items`.
- Les modifications ultérieures du catalogue/modèle **n'altèrent jamais** un inventaire existant (données copiées, pas référencées « à chaud »).

### 6.5 Autosave & accès concurrents
- Autosave **incrémental** (PATCH par ligne, debounced) ; brouillon persistant.
- **Verrouillage optimiste** : chaque inventaire/ligne porte un jeton de version (`row_version`/`updated_at`). Une écriture obsolète est **rejetée (409)** et l'UI propose une résolution — pas d'écrasement silencieux.
- Buffer **localStorage** côté client contre coupure réseau / fermeture navigateur ; reprise à la reconnexion.

### 6.6 Stock pharmacie & FEFO (transactionnel)
- Registre `stock_movements` **source de vérité** (type, lot, qté, stock avant/après, motif, utilisateur, horodatage).
- Opérations critiques en **transaction** + **verrou de ligne** (`SELECT … FOR UPDATE`).
- **FEFO** : lots proposés par date de péremption la plus proche ; **dérogation** réservée aux autorisés, avec **motif** et **trace**.
- **Stock négatif interdit** sauf permission admin spécifique.

### 6.7 Journalisation & audit
- Table `audit_log` **append‑only** : écriture via événements/observers, **aucune** route de modification/suppression.
- Champs : utilisateur, action, ressource + id, anciennes/nouvelles valeurs pertinentes, motif, IP/User‑Agent si pertinent, horodatage, **organisation_id**.
- **Jamais** de secrets/mots de passe/jetons dans les journaux.

---

## 7. Plan de développement (étapes, adapté SaaS)

> Chaque étape : analyse → migrations → code → **tests** → correction → revue sécurité →
> mise à jour de la doc → commit. On ne passe pas à l'étape suivante tant que l'actuelle
> n'est pas fonctionnelle et testée.

**Phase 0 — Socle**
0. Initialisation du projet (Laravel, Docker, CI, conventions, `.env.example`, README).

**Phase 1 — Fondation SaaS & sécurité**
1. Multi‑tenant : organisations, résolution de tenant, scope global, trait, tests anti‑fuite.
2. Authentification (Argon2id, reset, lockout, sessions, révocation).
3. RBAC dynamique (rôles, permissions, policies) + espace **super‑admin plateforme** (provisionnement d'organisation, 1er admin par invitation).

**Phase 2 — Référentiels**
4. Administration des utilisateurs (par organisation).
5. Véhicules + affectations utilisateurs↔véhicules.
6. Emplacements & sous‑emplacements.
7. Catalogue matériel + catégories.

**Phase 3 — Cœur inventaire**
8. Modèles d'inventaire + versions + éléments.
9. Planification & échéances.
10. Réalisation d'inventaire (UI tactile).
11. Autosave & gestion des conflits.
12. Validation & verrouillage (+ correction admin auditée).
13. Contrôles photographiques.

**Phase 4 — Maintenance & stock**
14. Anomalies (+ historique).
15. Réparations (+ historique).
16. Stock pharmacie & mouvements (transactions).
17. FEFO & alertes stock.

**Phase 5 — Transversal**
18. Historiques (inventaires, matériel, stock, anomalies).
19. Journal d'audit.
20. Recherche globale (respect strict des permissions).
21. Notifications internes (architecture ouverte e‑mail/push).
22. Exports PDF.
23. Personnalisation par organisation (logo, nom, thème) + plans/abonnements (activable).

**Phase 6 — Durcissement**
24. Complétion des tests automatisés (auth, RBAC, tenant, IDOR, FEFO, stock, upload, CSRF/XSS…).
25. Revue de sécurité complète.
26. Documentation de production (installation, déploiement, sauvegarde/restauration chiffrées, tâches planifiées).

---

## 8. Décisions techniques principales (résumé)

1. **PHP 8.3 / Laravel 11** — respecte le cahier, écosystème SaaS mûr.
2. **Multi‑tenant base partagée + `organisation_id`** — isolation réelle, simplicité maximale.
3. **Inertia + Vue 3 + Tailwind** — UI moderne et tactile (recommandé ; Livewire+Bootstrap en repli).
4. **spatie/laravel-permission** — RBAC dynamique et extensible.
5. **Snapshots d'inventaire + versions de modèle** — immutabilité de l'historique.
6. **Verrouillage optimiste + autosave + buffer local** — robustesse tablette.
7. **Registre de mouvements + transactions + FEFO** — intégrité du stock.
8. **Audit append‑only, fichiers privés signés, secrets hors Git** — sécurité par défaut.
9. **Docker** — distribuable en SaaS mutualisé **et** en self‑host mono‑tenant.

---

## 8 bis. Vulcain Desk — espace exploitant (back-office SaaS)

L'espace plateforme (super-admin) est conçu comme un **Desk** : le back-office de
l'exploitant pour piloter le SaaS comme un produit. Fondation posée en Phase 1.3
(authentification plateforme + provisionnement d'organisations + invitations) ; il
s'étoffera par pôles :

- **Administratif** : organisations/clients, contrats, plans & abonnements, facturation
  (paiement branché plus tard), quotas, cycle de vie (suspension/réactivation/réforme).
- **Technique** : supervision multi-tenant, support (tickets), journal d'audit transverse,
  état des sauvegardes, indicateurs d'usage, gestion des incidents.
- **Commercial** : prospects/CRM léger, démonstrations, onboarding, catalogue d'offres,
  suivi de conversion.

Principe : le Desk reste **strictement cloisonné** du métier des organisations (guard
`platform`, domaine central) et ne contourne jamais l'audit ni l'isolation des données.

## 9. Décisions validées (2026-07-06)

Les choix structurants ont été arbitrés par le porteur du projet :

| Décision | Choix retenu |
|---|---|
| Frontend | **Inertia.js + Vue 3 + Tailwind CSS** |
| Isolation multi‑tenant | **Base partagée + `organisation_id`** (migration base‑par‑client possible plus tard) |
| Périmètre SaaS | **Structure plans/abonnements/quotas + espace exploitant prêts, intégration de paiement (Stripe) reportée** |
| Accès & déploiement | **Sous‑domaine par organisation** (`caserne.vulcain.app`) + **mode self‑host mono‑tenant** via Docker |

Conformément au cahier des charges, **aucun développement, aucune migration, aucune table**
n'est réalisé à ce stade. Le développement (Phase 0 puis Phase 1) démarrera **après le
feu vert explicite** du porteur du projet.
