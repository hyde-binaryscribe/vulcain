# Documentation de progression — Vulcain

Suivi vivant du développement (exigé par le cahier des charges, §35).

## Étape en cours
- **Phases 0, 1, 2 (+ enrichissement), 3.1, 3.2** : terminées.
- **Renommage « Inventaire » → « Protocole »** (voir ci-dessous) : terminé.
- Prochaine : **Lot B — modèle d'emplacements (mobile/fixe, dépôts, matériel-conteneur,
  affichage parent), cible d'un protocole, distinction matériel/consommable en exécution**,
  puis **Phase 3.3 — Autosave avancé + validation + verrouillage**.

## Phase 4 — Alertes stock / péremption + FEFO pharmacie (terminée)
- Tableau de bord : cartes d'**alerte** cliquables (périmés, péremption < 30 j, stock bas,
  événements ouverts) — comptées côté serveur (`StockLot`, seuils matériel, événements).
- Pharmacie : tri **FEFO** (péremption la plus proche d'abord) + surlignage périmé/proche.

## Pharmacie — déclaration rapide des consommables (terminée)
- Espace **Pharmacie** (permission `pharmacy.manage`) : liste des consommables (stock,
  seuil, péremption la plus proche) + **déclaration rapide** d'un consommable (matériel
  suivi par lot). Les lots se gèrent ensuite depuis la fiche matériel. Gestion admin
  dédiée à venir. Entrée de menu « Pharmacie ». **Tests (+2 = 96 au total, verts)**.

## Phase 4 (1/2) — Événements + Kanban (terminée)
- Table `events` (type anomalie/réparation/autre, statut, priorité, rattachements
  véhicule/matériel/protocole, auteur, assigné). Migration **additive** (simple `migrate`).
- **Génération automatique** : à la validation d'un protocole, chaque anomalie devient un
  événement (`AnomalyEvents`), sans doublon ; HS/absent → priorité haute.
- **Tableau Kanban** (`Events/Index`) : colonnes À traiter / En cours / Résolu / Fermé,
  cartes (type, priorité, véhicule/matériel/assigné), déplacement ←/→ (tactile), création
  manuelle, suppression. Entrée de menu « Événements » (permission `anomalies.manage`).
- **Tests (+3 = 94 au total, verts)** : anomalie → événement, création, passage en colonne
  close (resolved_at).

## Phase 3.3 — Finalisation d'un protocole (terminée)
- **Récapitulatif** avant validation (modale) : contrôlés / non contrôlés / anomalies.
- **Observation obligatoire** sur chaque anomalie (série ou quantité/consommable) —
  bloquant à la validation, champ surligné en rouge tant qu'il manque.
- **Validation → verrouillage** : statut `validated`, `validated_at`, `duration_seconds`
  (durée lisible affichée). Écran en lecture seule ensuite.
- **Autosave débounced** pour la saisie des observations ; bannière d'erreur (flash.error).
- Sans changement de schéma (colonnes déjà présentes).
- **Tests (+1 = 92 au total, verts)** : validation refusée sans observation puis
  verrouillage avec durée.

## Lot B4 — Exécution différenciée matériel / consommable / quantité (terminé)
- Le snapshot éclate chaque matériel selon sa nature :
  - **série** : une ligne PAR n° de série attendu → états **Présent / Absent / Présent
    avec anomalie** (enum `ProtocolSerialState`) + **photo facultative** en cas d'anomalie
    (stockée sur le disque public, `storage:link` ajouté à l'entrypoint) ;
  - **consommable** : compter + **péremption la plus proche** à saisir, **dernière date
    connue en repère** (bouton de report rapide). L'exigence de péremption suit le réglage
    B2 : toujours en fixe, en mobile seulement si l'organisation la suit ;
  - **quantité** : +/- + états (inchangé).
- Champs `protocol_items` : `serial_number`, `last_known_expiry`, `expiry_required`,
  `observed_expiry`, `photo_path`. Écran tactile réécrit avec 3 types de cartes.
- Validation d'exécution branchée sur la nature (états série vs conforme/manquant…).
- **Tests (+2 = 91 au total, verts)** : éclatement série 1 ligne/n° série, exigence de
  péremption suivant le réglage mobile.

## Lot B3 — Cible de protocole par périmètre + exclusions (terminé)
- Un modèle de protocole ne cure plus le matériel à la main : il définit une **cible**
  = un **véhicule** (tout le matériel embarqué) OU un **emplacement** (+ « inclure les
  emplacements enfants »). Champs `scope_type`, `scope_id`, `include_children`.
- Service `ProtocolScope` : résout emplacements → matériels du périmètre (moins les
  `excluded_material_ids`), le véhicule déduit et le libellé de cible.
- `ProtocolSnapshot` réécrit : au démarrage, fige les matériels du périmètre. Les
  protocoles sur emplacement fixe / dépôt sont réservés aux gestionnaires.
- Éditeur de modèle : réglages (cible, types, fréquence) + **liste du périmètre** avec
  cases à cocher pour **exclure** un matériel. Table `protocol_template_items` supprimée.
- **Tests adaptés (89 au total, verts)** : création cible véhicule, exclusion, snapshot
  de périmètre immuable, autorisation vérificateur.

## Lot B2 — Réglage : suivi des péremptions en mobile (terminé)
- Réglage d'organisation `track_expiry_in_mobile` (stocké dans `organisations.settings`,
  **activé par défaut**) — `Organisation::tracksExpiryInMobile()`.
- Écran **Réglages** (permission `settings.manage`) avec l'option ; partagé au front via
  `tenant.settings` (consommé par l'exécution des protocoles au lot B4).
- **Tests (+3 = 90 au total, verts)** : défaut à vrai, désactivation par l'admin, accès refusé.

## Lot B1 — Emplacements mobile/fixe + chemin parent + matériel-conteneur (terminé)
- Emplacements typés **mobile** (à bord d'un véhicule) / **fixe** (dépôt, pièce de stock)
  — enum `LocationKind`. Colonne `kind` + rétro-remplissage (véhicule ⇒ mobile, sinon fixe).
- Un **matériel peut héberger un emplacement** (`holder_material_id`) : ex. la pochette d'un
  Lifepak 15 qui contient du consommable.
- Helper `Location::fullPath()` → « Véhicule › Parent › Emplacement » (préfixe véhicule pour
  le mobile, remontée de la hiérarchie). Affiché sur l'écran **Emplacements** et le
  **catalogue matériel** (matériel, exemplaires, lots).
- Écran Emplacements refondu : nature mobile/fixe (le véhicule ne s'affiche qu'en mobile),
  parent, matériel hôte, badges de nature, chemin complet.
- **Tests (+3 = 87 au total, verts)** : création fixe, véhicule requis en mobile, `fullPath`.

## Renommage « Inventaire » → « Protocole » + types (terminé)
- L'« inventaire » n'est plus le concept racine : c'est désormais un **type** parmi d'autres
  au sein d'un **Protocole**. Un modèle de protocole porte un ou plusieurs types
  (`inventaire`, `verification`, `controle_vehicule` — enum `ProtocolType`, extensible).
- Renommage complet du code : tables `protocol_templates` / `protocol_template_items` /
  `protocols` / `protocol_items`, modèles `Protocol*`, domaine `App\Domain\Protocol\*`,
  contrôleurs, routes (`/protocols`), permissions `protocols.perform | protocols.manage`,
  pages Vue `Pages/Protocols/*`, menu latéral « Protocoles ».
- Colonne `types` (JSON) sur le modèle **et** figée dans l'instantané du protocole réalisé ;
  cases à cocher côté admin (création + éditeur), badges de types côté liste/exécution.
- **84 tests verts**, build + Pint OK.

## Phase 3.2 — Réalisation d'inventaire (terminée)
- Tables `inventories` + `inventory_items` avec **instantané (snapshot) immuable** au
  démarrage : nom matériel/emplacement, quantité attendue, mode de suivi copiés — le
  catalogue peut changer sans jamais altérer un inventaire.
- **Démarrage** depuis un modèle (vérificateur limité à ses véhicules autorisés).
- **Écran tactile** (par emplacement) : quantité présente +/−, états Conforme / Manquant /
  HS / À remplacer, observation, progression ; enregistrement par élément.
- Consultation en lecture seule des inventaires validés ; un brouillon d'un autre
  vérificateur n'est pas ouvrable.
- Tableau de bord : compteur d'inventaires (total / en cours).
- Permission `inventories.perform | inventories.manage` ; anti-IDOR.
- **Tests (5, 84 au total, verts)** : snapshot immuable, accès véhicule autorisé,
  enregistrement, verrouillage post-validation, cloisonnement des brouillons.

## Phase 3.1 — Modèles d'inventaire + planification (terminée)
- Tables `inventory_templates` (véhicule, fréquence, version, actif) +
  `inventory_template_items` (matériel, emplacement, quantité attendue, ordre, photo requise).
- Fréquences (`InventoryFrequency` : quotidienne → trimestrielle + personnalisée avec jours).
- Écran **Modèles** (liste + création) et **éditeur** (réglages + éléments à contrôler,
  ajout depuis le catalogue, quantité inline, retrait). Permission `templates.manage`.
- **Version incrémentée** aux modifications de structure (préparation des snapshots immuables).
- Validations cloisonnées (véhicule/matériel/emplacement de l'organisation), anti-IDOR.
- Démo : « Inventaire hebdomadaire VSAV » avec 4 éléments.
- **Tests (5, 79 au total, verts)**.

## Phase 2.7 — Historique des actions (terminée)
- Table `activity_logs` (acteur, sujet polymorphe, action, valeurs avant→après, horodatage).
- Trait `RecordsActivity` : journalise automatiquement création / modification / suppression
  des véhicules, emplacements, matériels, exemplaires et lots (acteur + diff des champs,
  secrets exclus).
- **Historique par ressource** : sur la fiche matériel (matériel + ses exemplaires/lots) et
  la fiche véhicule (véhicule + emplacements + matériels).
- **Journal global** (`/activity`, permission `history.view`) + composant `HistoryList`.
- Cloisonné par organisation ; base du journal d'audit (§24).
- **Tests (4, 74 au total, verts)** : enregistrement création/modification (diff), cloisonnement,
  accès page, acteur.

## Phase 2.6 — Page véhicule dédiée (terminée)
- **Hub par véhicule** (`/vehicles/{id}`) : en-tête (type, statut, indicatif, immat., centre,
  km, utilisateurs autorisés), bandeau d'**alertes** (lots périmés, péremption < 30 j, sous
  seuil, non conformes), puis le **matériel groupé par emplacement** (suivi, stock/théorique,
  péremption, état). Liens vers les fiches matériel.
- Accès depuis les cartes véhicule (« Voir la fiche » / nom cliquable).
- **Test** de rendu + agrégation par emplacement (70 au total, verts).

## Phase 2.5 — Suivi du matériel (terminée)
- **3 modes de suivi** par matériel : `quantity` (stock courant `current_qty`),
  `serial` (exemplaires `material_items` avec n° de série/état/prochain contrôle),
  `lot` (`stock_lots` avec n° de lot, quantité, **péremption** — base FEFO).
- **Page détail matériel** adaptée au mode : réglage du stock / gestion des exemplaires /
  gestion des lots (alertes péremption < 30 j et périmé).
- Stock calculé selon le mode ; alerte sous seuil minimal.
- Anti-IDOR sur exemplaires et lots (autre organisation → 404).
- Démo enrichie : DSA (unitaire), Sérum physiologique (2 lots dont un proche péremption).
- **Tests (6, 69 au total, verts)**.

## Phase 2.3 — Emplacements (terminée)
- Hiérarchie parent/enfant, rattachement véhicule ou réserve globale, ordre, actif.
- Écran table + panneau de création ; validations cloisonnées ; anti auto-parent. 4 tests.

## Phase 2.4 — Catalogue matériel + catégories (terminée)
- Tables `material_categories` + `materials` (référence, catégorie, emplacement, mode de
  suivi quantité/unité, quantités théorique/min, n° série, péremption, prochain contrôle,
  statut, observations, soft delete) + enum `MaterialStatus` (conforme / manquant / HS /
  à remplacer / en réparation / indisponible). Index prêts pour QR/codes-barres.
- Écran Matériel : table + **mise à jour rapide du statut** (avec note), formulaire d'ajout,
  gestion des catégories, édition complète en modale (permission `catalog.manage`).
- Validations cloisonnées (catégorie/emplacement de l'organisation), anti-IDOR.
- **Données de démonstration** (org « demo ») : VSAV 01, 3 emplacements, 3 catégories,
  3 matériels (Collier cervical, Couverture de survie, BAVU).
- **Tests (7, 63 au total, verts)**.

## Phase 2.2 — Véhicules + affectations (terminée)
- Table `vehicles` (type, indicatif, immatriculation, centre, statut, mise en service,
  kilométrage, observations, soft delete) + statuts (`VehicleStatus` : disponible /
  indisponible / maintenance / réparation / réformé).
- Écran **Véhicules** en cartes (thème du secteur), CRUD via modale (permission
  `vehicles.manage`), suppression logique.
- **Affectations** utilisateurs↔véhicules (`vehicle_user`) : seuls les utilisateurs de
  l'organisation peuvent être affectés (les IDs étrangers sont ignorés).
- Anti-IDOR : binding cloisonné (véhicule d'une autre organisation → 404).
- **Tableau de bord** enrichi : compteurs véhicules / disponibles / utilisateurs actifs.
- **Tests (6, 52 au total, verts)** : accès (manager/refus), CRUD, affectations (filtrage
  inter-organisation), IDOR.

## Phase 2.1 — Administration des utilisateurs (terminée)
- Écran **Utilisateurs** (permission `users.manage`) : liste + recherche (nom/e-mail),
  rôle, statut, dernière connexion.
- **Invitation** d'un utilisateur avec choix du rôle (réutilise le flux d'invitation sécurisé).
- **Édition** (grade, rôle, actif/inactif) via modale ; garde-fou **anti auto-verrouillage**
  (impossible de retirer son propre accès administrateur).
- **Invitations en attente** : renvoyer / annuler.
- Anti-IDOR : binding de route cloisonné (utilisateur d'une autre organisation → 404).
- Notification d'invitation rendue **générique** (libellé du rôle).
- **Tests (6, 46 au total, verts)** : accès (admin/refus), invitation, édition rôle/statut,
  garde-fou self, IDOR inter-organisation.

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
