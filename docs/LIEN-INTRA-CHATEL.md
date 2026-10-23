# Lien Vulkain ↔ intra-Chatel

intra-Chatel (application interne, `https://intra-Chatel.fr`) a ses propres formulaires de **prise de poste** et de **fin de service**. Le lien les partage avec Vulkain, en test :

- **Entrant** (intra-Chatel → Vulkain) : chaque formulaire validé est envoyé à l'API v1 et devient une session de service Vulkain, comme une prise de service faite sur le terrain.
- **Sortant** (Vulkain → intra-Chatel) : à chaque prise et fin de service (terrain Vulkain ou intra-Chatel), Vulkain envoie une notification signée à l'adresse déclarée.

Le prompt de configuration côté intra-Chatel est dans `docs/PROMPT-GROK-INTRA-CHATEL.md`.

## Règle du lien (décision du porteur)

**intra-Chatel fait référence partout.** Vulkain **lit** les données de base d'intra-Chatel (agents, véhicules, sacs, bouteilles), les **associe** à ses propres fiches et **saisit des entrées** (prises et fins de service, notifications). Strictement rien de plus : Vulkain ne modifie jamais les données de base d'intra-Chatel, et ne réécrit pas non plus ses propres fiches à partir d'elles.

## Décisions

- Même socle que le lien Itineris (`docs/LIEN-ITINERIS.md`) : jeton `vk_…` par organisation (Réglages › Lien Itineris), réservé au secteur transport sanitaire, 120 requêtes par minute.
- Un véhicule est désigné par son **uuid** (`vehicle_uuid`), par son **id intra-Chatel** (`vehicle_ref`, via l'association) ou par son **indicatif** (`vehicle_callsign`, « A9 »), à condition d'être unique dans l'organisation (sinon 422 `ambiguous_vehicle`).
- Les agents sont connus par leurs **initiales** dans intra-Chatel. Vulkain garde le nom saisi (`opened_by_name`, `partner_name`, `closed_by_name`) et relie la session au compte associé (référentiel des agents), sinon à celui dont l'**identifiant interne** (`username`) est identique, s'il n'y en a qu'un.
- Une seule session ouverte par véhicule : une prise de poste clôture la session en cours (passation, `close_reason: handover`), comme sur le terrain. La réponse le signale (`handover`), de quoi afficher « Session de HT non clôturée ».
- **Idempotence** : chaque formulaire porte un `external_id` choisi par intra-Chatel. Le renvoyer ne crée rien de plus (200, `replayed: true`). Unique par organisation, séparément pour les prises de poste et les fins de service.
- Le kilométrage passe par `Vehicle::recordMileage()` (le compteur ne recule jamais, saut de plus de 2 000 km signalé), sources `prise_de_service` / `fin_de_service`, auteur = initiales.
- Les heures envoyées (`opened_at`, `closed_at`) ne sont jamais prises dans le futur ; une clôture n'est jamais antérieure à l'ouverture.

## Formulaires → contrôles

Les champs deviennent des contrôles Vulkain (mêmes lignes que les protocoles de service : `label`, `type`, `value`, `percent`, `unit`, `alert`), chacun avec une référence stable `ref`. Ils s'affichent dans Suivi de service (badge « intra-Chatel ») et dans `GET /api/v1/vehicles/checks` (Itineris). Le niveau de la première bouteille renseignée sert de niveau d'oxygène pour Itineris (`o2`).

| Formulaire intra-Chatel | Champ JSON | `ref` | Type | Alerte |
|---|---|---|---|---|
| Véhicule | `vehicle_uuid` ou `vehicle_callsign` | — | — | — |
| Créateur de la session / Initiales | `opened_by` / `closed_by` | — | — | — |
| Équipier (binôme) | `partner` | — | — | — |
| Kilométrage | `mileage` (km) | — | `open_mileage` / `close_mileage` | — |
| Niveau de carburant | `fuel_percent` (0–100 ; huitièmes arrondis : 1/8 → 13, 1/4 → 25) | `fuel_percent` | jauge % | ≤ 25 % |
| Bouteille n — N° série | `oxygen[].serial_number` (mis en majuscules) | `oxygen.n.serial_number` | texte | — |
| Bouteille n — niveau | `oxygen[].level_percent` (0–100, `null` = « À SAISIR ») | `oxygen.n.level_percent` | jauge % | ≤ 25 % |
| Bouteille n — Scellée | `oxygen[].sealed` | `oxygen.n.sealed` | case | — |
| Véhicule chaud | `fluids.hot_engine` | `fluids.hot_engine` | case | — |
| Liquide refroidissement | `fluids.coolant_percent` | `fluids.coolant_percent` | jauge % | ≤ 25 % |
| Niveau huile | `fluids.oil_percent` | `fluids.oil_percent` | jauge % | ≤ 25 % |
| Apport d'huile ? | `fluids.oil_added` | `fluids.oil_added` | case | — |
| Pression pneus faite ? | `fluids.tyre_pressure_done` | `fluids.tyre_pressure_done` | case | — |
| Sac assigné | `bag.name` | `bag.name` | texte | — |
| N° Scellé Sac/Malette | `bag.seal_number` (majuscules) | `bag.seal_number` | texte | — |
| Commentaires | `comments[]` (réunis, une ligne chacun) | — | `open_notes` / `close_notes` | — |

La fin de service ne porte que kilométrage, oxygène et commentaires. Bouteilles : 4 au plus ; `position` (1–4) vaut par défaut le rang dans la liste. Les alertes sont visibles dans les contrôles ; elles n'ouvrent pas d'anomalie (étape suivante si besoin).

## API v1 (entrant)

Authentification : `Authorization: Bearer vk_…`, corps JSON. Erreurs : `{ error, code }` ; 422 `validation_failed` avec `errors` par champ.

| Route | Rôle | Réponses |
|---|---|---|
| `GET /api/v1/vehicles` | Correspondance indicatif → uuid | `{ data: Vehicle[] }` |
| `GET /api/v1/services/current?vehicle_uuid=…` (ou `vehicle_callsign=`) | Préremplir le formulaire | `{ data: { vehicle, open_service, last_oxygen } }` |
| `POST /api/v1/services/open` | Prise de poste | 201 `{ data: Service, handover, replayed: false }` ; 200 `replayed: true` |
| `POST /api/v1/services/close` | Fin de service | 200 `{ data: Service, replayed }` ; 409 `no_open_service` |
| `GET /api/v1/services?since=ISO8601&vehicle_uuid=&limit=` | Rattrapage (polling) | `{ data: Service[] }`, modifiés depuis `since` (24 h par défaut), max 200 |

Codes : 401 `unauthorized`, 404 `vehicle_not_found`, 409 `no_open_service`, 422 `validation_failed` / `ambiguous_vehicle` / `vehicle_not_linked`, 429 trop de requêtes.

`current` : `vehicle` (`uuid`, `callsign`, `name`, `mileage`, `mileage_at`) ; `open_service` = service en cours ou `null` ; `last_oxygen` = bouteilles du dernier service intra-Chatel (`source: "service"`), sinon de la dotation Oxygène embarqué (`source: "dotation"`) : `position`, `serial_number`, `level_percent`.

`Service` : `vehicle` (`uuid`, `callsign`, `name`), `source` (`vulkain` | `intra_chatel`), `external_id`, `close_external_id`, `open`, `opened_at`, `opened_by`, `partner`, `open_mileage`, `open_checks`, `open_notes`, `closed_at`, `closed_by`, `close_reason` (`manual` | `handover`), `close_mileage`, `close_checks`, `close_notes`, `updated_at`. Contrôle : `ref`, `label`, `type`, `value`, `percent`, `unit`, `alert` (`severity`, `message`) ou `null`. Jamais de photo ni d'id interne.

## Référentiels intra-Chatel (lecture seule)

intra-Chatel publie la **liste complète** de chaque référentiel ; Vulkain en garde une copie (`intra_chatel_records`) et l'associe à ses fiches. Une fiche absente d'une publication est marquée « absente », jamais supprimée, et garde son association.

| Route | Champs de chaque fiche (`data[]`) | Associée à |
|---|---|---|
| `PUT /api/v1/intra-chatel/agents` | `id`*, `initials`*, `first_name`, `last_name`, `active` | agent Vulkain (Utilisateurs) |
| `PUT /api/v1/intra-chatel/vehicles` | `id`*, `callsign`*, `registration`, `name`, `active` | véhicule |
| `PUT /api/v1/intra-chatel/bags` | `id`*, `name`*, `vehicle_id` (id intra-Chatel), `seal_number`, `active` | sac (emplacement de type sac) |
| `PUT /api/v1/intra-chatel/oxygen-bottles` | `id`*, `serial_number`*, `capacity_l`, `vehicle_id` (id intra-Chatel), `active` | bouteille de la dotation Oxygène embarqué |

\* obligatoire. Corps : `{ "data": [ … ] }`, 2 000 fiches au plus, `id` uniques. Réponse : `{ data: { received, created, updated, missing, linked } }`. Les autres champs envoyés sont conservés tels quels. `GET` sur la même route renvoie la copie détenue par Vulkain : `id`, `label`, `present`, `synced_at`, `vulkain` (fiche associée : `uuid`/`callsign`/`name` pour un véhicule, `name` sinon, `serial_number` pour une bouteille) et `link_mode`.

Association :
- **automatique** à la publication, si une seule fiche Vulkain correspond exactement : initiales = identifiant interne de l'agent ; indicatif, sinon immatriculation (sans tirets ni espaces) ; nom du sac ; n° de série de la bouteille. Une association existante n'est jamais remplacée.
- **manuelle** dans Réglages › Référentiels intra-Chatel (journalisée). Seule l'association change, jamais les fiches.

Usage dans l'API de service : `vehicle_ref` (id intra-Chatel du véhicule) désigne le véhicule associé (422 `vehicle_not_linked` s'il ne l'est pas) ; un `vehicle_callsign` inconnu de Vulkain est cherché parmi les indicatifs publiés par intra-Chatel ; des initiales (`opened_by`, `partner`, `closed_by`) désignent d'abord l'agent associé à l'agent intra-Chatel de ces initiales, puis l'identifiant interne.

## Notifications sortantes

Réglages › Notifications sortantes : nom, adresse de réception ; le **secret** `whsec_…` n'est montré qu'une fois. Bouton « Tester » : envoie un `ping`. Création et suppression sont journalisées.

- Événements : `service.opened`, `service.closed` (toutes origines, `data.source` le précise ; intra-Chatel ignore ses propres envois `source: "intra_chatel"`), `ping`.
- Corps : `{ id, event, created_at, data: Service }` en `POST` JSON.
- En-têtes : `X-Vulkain-Event`, `X-Vulkain-Delivery` (= `id`, pour dédoublonner), `X-Vulkain-Signature: t=<horodatage unix>,v1=<hex>` avec `v1 = HMAC-SHA256(secret, "<t>.<corps brut>")`.
- Réponse attendue : 2xx en moins de 5 s. Sinon nouvelle tentative après 1, 5, 15, 60 puis 240 min (6 tentatives), puis abandon (`webhook_deliveries.status = failed`). Pas de redirection suivie.
- L'envoi part après la réponse HTTP : le terrain n'attend jamais intra-Chatel. Les nouvelles tentatives passent par `php artisan vulcain:retry-webhooks` (planifiée toutes les 5 minutes) ou l'URL `/cron/webhooks/{CRON_TOKEN}` (tâche Plesk « Récupérer une URL »).
- Adresses du réseau privé (localhost, 10.x, 192.168.x, 169.254.x…) refusées, sauf `WEBHOOKS_ALLOW_PRIVATE_HOSTS=true` (intra-Chatel joignable seulement depuis l'intranet).

## Étapes suivantes

- Ouvrir une anomalie Vulkain sur alerte (carburant, oxygène, liquides bas).
- Signaler dans Vulkain les écarts entre une fiche intra-Chatel et la fiche associée (immatriculation, n° de série), sans rien corriger automatiquement.
- Planning des services (non retenu pour l'instant).
