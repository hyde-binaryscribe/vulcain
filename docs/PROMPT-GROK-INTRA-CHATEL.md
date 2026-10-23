# Prompt Grok — brancher intra-Chatel sur Vulkain

À coller tel quel dans Grok, avec le code d'intra-Chatel ouvert. Remplacer les trois valeurs entre chevrons par celles affichées dans Vulkain › Réglages.

---

Tu travailles sur **intra-Chatel** (`https://intra-Chatel.fr`), notre application interne. Elle a deux formulaires : **PRISE DE POSTE** et **FIN DE SERVICE**. Je veux, **à des fins de test**, les partager avec **Vulkain** (notre logiciel de gestion de flotte) dans les deux sens. Le contrat ci-dessous est fixé côté Vulkain : ne le change pas, adapte intra-Chatel. Utilise la pile et les conventions déjà en place dans intra-Chatel. Ne mets aucun secret dans le code ou dans Git : passe par la configuration (variables d'environnement ou réglages admin).

## Configuration à ajouter

| Clé | Valeur |
|---|---|
| `VULKAIN_API_URL` | `<https://app.vulkain.eu/api/v1>` (adresse affichée dans Vulkain › Réglages › Lien Itineris) |
| `VULKAIN_API_TOKEN` | `<vk_…>` (jeton créé dans Vulkain › Réglages › Lien Itineris) |
| `VULKAIN_WEBHOOK_SECRET` | `<whsec_…>` (secret donné à la création de l'adresse dans Vulkain › Réglages › Notifications sortantes) |
| `VULKAIN_ENABLED` | `true` / `false` : coupe tout le lien sans toucher au code |

Chaque appel : en-têtes `Authorization: Bearer ${VULKAIN_API_TOKEN}`, `Accept: application/json`, `Content-Type: application/json`, délai max 10 s. Limite : 120 requêtes/minute.

## 1. Sens intra-Chatel → Vulkain

### 1.a Préremplir le formulaire (à la sélection du véhicule)

`GET {VULKAIN_API_URL}/services/current?vehicle_callsign=A9` (ou `?vehicle_uuid=…` si tu as l'uuid)

```json
{
  "data": {
    "vehicle": { "uuid": "9b2e…", "callsign": "A9", "name": "Ambulance 9", "mileage": 118084, "mileage_at": "2026-10-23T07:02:11+02:00" },
    "open_service": {
      "source": "vulkain", "open": true, "opened_at": "2026-10-22T19:00:00+02:00",
      "opened_by": "HT", "partner": "MJM", "open_mileage": 117950, "...": "…"
    },
    "last_oxygen": [
      { "position": 1, "serial_number": "F337F1L", "level_percent": 40, "source": "service" },
      { "position": 2, "serial_number": "F35YA5H", "level_percent": 10, "source": "service" }
    ]
  }
}
```

Usage :
- **Prise de poste** : si `open_service` n'est pas `null`, afficher le bandeau « ⚠️ Session de {open_service.opened_by} non clôturée. » Préremplir les N° de série des bouteilles avec `last_oxygen` (par `position`).
- **Fin de service** : afficher le rappel « {opened_by} / {partner} — {vehicle.mileage} km ». Préremplir les N° de série avec `last_oxygen`.
- Si Vulkain ne répond pas, le formulaire reste utilisable sans préremplissage. Ne bloque jamais la saisie.

### 1.b Envoyer la prise de poste (au clic « Valider la Prise de Poste »)

`POST {VULKAIN_API_URL}/services/open`

```json
{
  "external_id": "pdp-6f1c2a9e-1b7d-4a8e-9a43-0c5d2e7f8b10",
  "vehicle_callsign": "A9",
  "opened_by": "AJ",
  "partner": "MJM",
  "opened_at": "2026-10-23T09:03:00+02:00",
  "mileage": 118084,
  "fuel_percent": 100,
  "oxygen": [
    { "position": 1, "serial_number": "F337F1L", "level_percent": 80, "sealed": true },
    { "position": 2, "serial_number": "F35YA5H", "level_percent": null, "sealed": false }
  ],
  "fluids": {
    "hot_engine": false,
    "coolant_percent": 60,
    "oil_percent": 50,
    "oil_added": false,
    "tyre_pressure_done": true
  },
  "bag": { "name": "Sac 2", "seal_number": "C2164894" },
  "comments": ["Rayure portière arrière"]
}
```

Correspondance des champs du formulaire :
- **Véhicule** → `vehicle_callsign` (« A9 »). Si tu as l'uuid Vulkain, envoie plutôt `vehicle_uuid`. La liste des véhicules avec leurs uuid : `GET {VULKAIN_API_URL}/vehicles` (`data[].uuid`, `data[].callsign`).
- **Créateur de la session** → `opened_by` (initiales, obligatoire). **Équipier (binôme)** → `partner` (initiales ou nom, `null` si aucun).
- **Kilométrage** → `mileage` (entier, km).
- **Niveau de carburant** → `fuel_percent`, entier de 0 à 100 (le curseur en huitièmes : 1/8 → 13, 1/4 → 25, 1/2 → 50, 1 → 100).
- **Matériel Oxygène**, une entrée par bouteille : `position` (1, 2…), `serial_number`, `level_percent` (0–100), `sealed` (case « Scellée »). Curseur resté sur « À SAISIR » → `level_percent: null`. **Il faut que le curseur des bouteilles soit converti en pourcentage** ; s'il est en bar ou en litres, dis-le-moi au lieu d'inventer une conversion.
- **Niveaux & Pneus** : « Véhicule chaud » → `fluids.hot_engine` ; « Liquide refroidissement » → `fluids.coolant_percent` ; « Niveau Huile » → `fluids.oil_percent` (0–100, `null` si « À SAISIR ») ; « Apport d'huile ? » → `fluids.oil_added` ; « Pression Pneus faite ? » → `fluids.tyre_pressure_done` (Oui = `true`, Non = `false`).
- **Sac assigné** → `bag.name` ; **N° Scellé Sac/Malette** → `bag.seal_number`.
- **Commentaires** → `comments` : liste de textes, y compris le commentaire en cours non « ajouté » (le formulaire l'enregistre déjà).
- `opened_at` : heure de validation, ISO 8601 avec fuseau.

Réponse **201** :

```json
{
  "data": { "vehicle": { "uuid": "9b2e…", "callsign": "A9" }, "source": "intra_chatel", "external_id": "pdp-6f1c…", "open": true, "opened_by": "AJ", "partner": "MJM", "open_checks": [ { "ref": "fuel_percent", "label": "Niveau de carburant", "type": "gauge", "value": 100, "percent": 100, "unit": "%", "alert": null } ], "...": "…" },
  "handover": { "opened_by": "HT", "opened_at": "2026-10-22T19:00:00+02:00" },
  "replayed": false
}
```

`handover` non `null` : Vulkain a clôturé la session restée ouverte (celle de HT). Garde l'info dans intra-Chatel si tu la traces.

### 1.c Envoyer la fin de service (au clic « Clôturer le Service »)

`POST {VULKAIN_API_URL}/services/close`

```json
{
  "external_id": "fds-2c9d7e41-5a0b-4f3e-8c21-9e6b1a4d7f02",
  "vehicle_callsign": "A10",
  "closed_by": "AJ",
  "closed_at": "2026-10-23T19:05:00+02:00",
  "mileage": 118190,
  "oxygen": [
    { "position": 1, "serial_number": "F337F1L", "level_percent": 40, "sealed": false },
    { "position": 2, "serial_number": "F35YA5H", "level_percent": 10, "sealed": false }
  ],
  "comments": ["RAS"]
}
```

« Initiales (Celui qui ferme la session) » → `closed_by` (obligatoire). Réponse 200 `{ "data": Service, "replayed": false }`. **409 `no_open_service`** : aucun service en cours pour ce véhicule dans Vulkain. Affiche un message clair (« Aucune prise de poste connue de Vulkain pour ce véhicule ») sans bloquer la clôture côté intra-Chatel.

### 1.d Règles d'envoi (obligatoires)

1. **`external_id`** : un identifiant unique par formulaire validé (préfixe `pdp-` ou `fds-` + UUID v4), **généré une fois** et enregistré avec le formulaire. Un renvoi avec le même `external_id` ne crée rien de plus (réponse 200, `replayed: true`).
2. **Ne jamais bloquer l'agent** : la validation est d'abord enregistrée dans intra-Chatel, puis envoyée à Vulkain. L'envoi se fait en tâche de fond (file ou tâche planifiée), pas dans la requête du clic si ta pile le permet.
3. **Nouvelles tentatives** : en cas d'erreur réseau, de délai dépassé, de 429 ou de 5xx, renvoyer **le même corps avec le même `external_id`** après 1, 5, 15, 60 min, puis signaler à un admin. Ne pas renvoyer en cas de 401, 404 ou 422 : journaliser `code` et `errors` et signaler à un admin.
4. **Ordre** : n'envoie une fin de service qu'après le succès de la prise de poste du même véhicule si les deux sont en attente.
5. Journalise chaque appel (route, `external_id`, statut HTTP, `code`), **sans le jeton**.

Codes d'erreur : 401 `unauthorized` (jeton absent ou révoqué), 404 `vehicle_not_found`, 409 `no_open_service`, 422 `validation_failed` (détail dans `errors`) ou `ambiguous_vehicle` (deux véhicules ont cet indicatif : utiliser `vehicle_uuid`), 429 trop de requêtes.

## 2. Sens Vulkain → intra-Chatel (notifications signées)

Crée une route publique `POST /api/vulkain/webhook` (adapte le chemin à tes conventions et donne-moi l'URL finale : je la déclare dans Vulkain).

Vulkain y envoie, à chaque prise et fin de service faite **sur le terrain Vulkain ou dans intra-Chatel** :

```
POST /api/vulkain/webhook
Content-Type: application/json
X-Vulkain-Event: service.opened | service.closed | ping
X-Vulkain-Delivery: 5f0c…-uuid
X-Vulkain-Signature: t=1729667000,v1=3b6f…(hex 64)

{ "id": "5f0c…-uuid", "event": "service.closed", "created_at": "2026-10-23T19:05:02+02:00", "data": { …Service… } }
```

`data` a la même forme que la réponse de `/services/open` : `vehicle` (`uuid`, `callsign`, `name`), `source`, `external_id`, `close_external_id`, `open`, `opened_at`, `opened_by`, `partner`, `open_mileage`, `open_checks[]`, `open_notes`, `closed_at`, `closed_by`, `close_reason` (`manual` ou `handover`), `close_mileage`, `close_checks[]`, `close_notes`, `updated_at`. Chaque contrôle : `ref`, `label`, `type`, `value`, `percent`, `unit`, `alert` (`severity`, `message`) ou `null`.

Traitement, dans cet ordre :
1. Lire le **corps brut** (avant tout décodage JSON).
2. Vérifier la signature : extraire `t` et `v1` de `X-Vulkain-Signature`, calculer `HMAC-SHA256(VULKAIN_WEBHOOK_SECRET, t + "." + corps_brut)` en hexadécimal, **comparer en temps constant** (`hash_equals` en PHP, `crypto.timingSafeEqual` en Node). Refuser si `|maintenant - t| > 300 s`. Échec → **401**, rien d'autre.
3. Dédoublonner par `X-Vulkain-Delivery` : déjà traité → **200** sans rien refaire.
4. `event == "ping"` → **200**.
5. `data.source == "intra_chatel"` → c'est notre propre envoi qui revient : **200** sans rien faire (pas de boucle).
6. Sinon enregistrer : `service.opened` = un agent a pris le véhicule `data.vehicle.callsign` dans Vulkain (mettre à jour l'état « session ouverte » utilisé par le bandeau « Session de … non clôturée ») ; `service.closed` = le service est fini (retirer cet état, garder kilométrage et niveaux).
7. Répondre **2xx en moins de 5 secondes** (traitement lourd en tâche de fond). Sinon Vulkain retente après 1, 5, 15, 60 et 240 min.

Exemple de vérification en PHP :

```php
$raw = file_get_contents('php://input');
preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $_SERVER['HTTP_X_VULKAIN_SIGNATURE'] ?? '', $m);
$ok = $m && abs(time() - (int) $m[1]) <= 300
    && hash_equals(hash_hmac('sha256', $m[1].'.'.$raw, getenv('VULKAIN_WEBHOOK_SECRET')), $m[2]);
if (! $ok) { http_response_code(401); exit; }
```

En Node :

```js
const [, t, v1] = /^t=(\d+),v1=([0-9a-f]{64})$/.exec(req.get('X-Vulkain-Signature') || '') || [];
const expected = crypto.createHmac('sha256', process.env.VULKAIN_WEBHOOK_SECRET).update(`${t}.${rawBody}`).digest('hex');
const ok = t && Math.abs(Date.now() / 1000 - Number(t)) <= 300
  && crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(v1));
```

## 3. Tests à me livrer

1. `curl` : `GET /vehicles` puis `GET /services/current?vehicle_callsign=A9` répondent 200 avec le jeton, 401 sans.
2. Une prise de poste réelle apparaît dans Vulkain › Suivi de service avec le badge « intra-Chatel », les niveaux et les initiales.
3. Le même formulaire renvoyé deux fois → une seule session (`replayed: true` au second envoi).
4. Fin de service → la session est clôturée dans Vulkain avec le kilométrage.
5. Vulkain › Réglages › Notifications sortantes › « Tester » → la route webhook répond 200 (« Test reçu »).
6. Une prise de service faite sur le terrain Vulkain → intra-Chatel affiche le bandeau « Session de … non clôturée » pour ce véhicule.
7. Signature modifiée d'un caractère → 401.
8. Vulkain coupé ou jeton faux → le formulaire intra-Chatel s'enregistre quand même, l'envoi est retenté puis signalé.

Livre : la liste des fichiers modifiés, l'URL finale du webhook, et les points où tu as dû supposer quelque chose (notamment l'unité du curseur des bouteilles).
