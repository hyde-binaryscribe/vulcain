# Lien Vulkain ↔ intra-Chatel

intra-Chatel (application interne, `https://intra-Chatel.fr`) a ses propres formulaires de **prise de poste** et de **fin de service**. Le lien les partage avec Vulkain, en test :

- **Entrant** (intra-Chatel → Vulkain) : chaque formulaire validé est envoyé à l'API v1 et devient une session de service Vulkain, comme une prise de service faite sur le terrain.
- **Sortant** (Vulkain → intra-Chatel) : à chaque prise et fin de service (terrain Vulkain ou intra-Chatel), Vulkain envoie une notification signée à l'adresse déclarée.

Le prompt de configuration côté intra-Chatel est dans `docs/PROMPT-GROK-INTRA-CHATEL.md`.

## Décisions

- Même socle que le lien Itineris (`docs/LIEN-ITINERIS.md`) : jeton `vk_…` par organisation (Réglages › Lien Itineris), réservé au secteur transport sanitaire, 120 requêtes par minute.
- Un véhicule est désigné par son **uuid** (`vehicle_uuid`) ; l'**indicatif** (`vehicle_callsign`, « A9 ») est accepté pour le test, à condition d'être unique dans l'organisation (sinon 422 `ambiguous_vehicle`).
- Les agents sont connus par leurs **initiales** dans intra-Chatel. Vulkain garde le nom saisi (`opened_by_name`, `partner_name`, `closed_by_name`) et relie la session au compte dont l'**identifiant interne** (`username`) est identique, s'il n'y en a qu'un. Pour relier un agent : mettre ses initiales comme identifiant interne dans Utilisateurs.
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

Codes : 401 `unauthorized`, 404 `vehicle_not_found`, 409 `no_open_service`, 422 `validation_failed` / `ambiguous_vehicle`, 429 trop de requêtes.

`current` : `vehicle` (`uuid`, `callsign`, `name`, `mileage`, `mileage_at`) ; `open_service` = service en cours ou `null` ; `last_oxygen` = bouteilles du dernier service intra-Chatel (`source: "service"`), sinon de la dotation Oxygène embarqué (`source: "dotation"`) : `position`, `serial_number`, `level_percent`.

`Service` : `vehicle` (`uuid`, `callsign`, `name`), `source` (`vulkain` | `intra_chatel`), `external_id`, `close_external_id`, `open`, `opened_at`, `opened_by`, `partner`, `open_mileage`, `open_checks`, `open_notes`, `closed_at`, `closed_by`, `close_reason` (`manual` | `handover`), `close_mileage`, `close_checks`, `close_notes`, `updated_at`. Contrôle : `ref`, `label`, `type`, `value`, `percent`, `unit`, `alert` (`severity`, `message`) ou `null`. Jamais de photo ni d'id interne.

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
- Mettre à jour la dotation Oxygène embarqué (n° de série, changement de bouteille) depuis les relevés intra-Chatel, une fois l'unité du curseur confirmée (% ou bar).
- Lister les sacs Vulkain pour le menu « Sac assigné ».
