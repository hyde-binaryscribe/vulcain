# Lien Vulkain ↔ Itineris

Itineris (régulation de transport sanitaire) et Vulkain restent deux applications séparées. Le contrat complet (décisions du porteur, répartition des champs, demandes d'indisponibilité) est tenu côté Itineris : `docs/lien-vulkain.md` du dépôt `itineris`. Ce document décrit ce que Vulkain expose.

## Décisions qui touchent Vulkain

- Le lien n'existe que pour les organisations du secteur **transport sanitaire** (`ambulance_privee`) : Itineris ne gère que ce secteur.
- Un véhicule est identifié par son **uuid public**, jamais par l'id interne, l'immatriculation ou l'indicatif.
- Vulkain est propriétaire de l'identité physique (immatriculation, VIN, modèle, mise en circulation), du boîtier GPS, du kilométrage, du contrôle technique, de l'assurance, de l'entretien, des documents, des vérifications et de la désinfection.
- **Vulkain ne rend jamais un véhicule indisponible dans Itineris** : il en fera la demande, la régulation décide (étape suivante du lien).

## Étape 1 (en place) : lecture

- `vehicles.uuid` : identifiant public, créé automatiquement.
- `vehicles.vin` : numéro de série (17 caractères, sans I, O ni Q), saisi dans la fiche véhicule, mis en majuscules.
- `vehicles.version` : +1 à chaque modification d'un champ partagé (`Vehicle::SHARED_FIELDS`), hors kilométrage. Sert à refuser une écriture faite sur une version dépassée (étape 2).
- Jetons d'accès : Réglages › Lien Itineris (droit `settings.manage`). Le jeton (`vk_…`) n'est montré qu'une fois ; seule son empreinte SHA-256 est stockée (`api_tokens`). Création et révocation sont journalisées.

### API v1

Authentification : `Authorization: Bearer vk_…`. Jeton inconnu, révoqué ou organisation suspendue : 401 `{ error, code: "unauthorized" }`.

| Route | Réponse |
|---|---|
| `GET /api/v1/vehicles` | `{ data: Vehicle[] }`, véhicules actifs de l'organisation du jeton |
| `GET /api/v1/vehicles/{uuid}` | `{ data: Vehicle }`, y compris un véhicule supprimé (`deleted: true`) ; 404 sinon |
| `GET /api/v1/vehicles/checks` | `{ data: Checks[] }`, vérifications de tout le parc en une requête |

`Checks` : `uuid` ; `service` (dernière prise de service : `open`, `opened_at`, `opened_by`, `partner`, `closed_at`, `closed_by`, `checks` et `end_checks` = contrôles `{ label, type, value, alert: { severity, message } | null }`, jamais de photo) ; `disinfection` (`state` none / ok / soon / overdue / never, `label`, `due_at`, `last` = dernière opération par nature avec `label`, `performed_at`, `by`, `protocol`) ; `anomalies` ouvertes (`title`, `priority`, `status`, `created_at`) ; `o2` = dernier niveau d'oxygène (`label`, `value`, `unit`, `percent` pour une jauge, `alert`, `at`), pris à la fin de service si elle en contient un, sinon à la prise de service. Chaque contrôle porte aussi `percent`, `unit` et `key` (`o2`).

Niveau d'oxygène : le champ est choisi dans Protocoles de service (« Transmis à Itineris : Niveau d'oxygène ») ; sans réglage, une jauge ou un nombre dont le libellé contient « oxygène » ou « O2 » est reconnu. Les contrôles enregistrent désormais l'id du champ, l'unité et ce partage.

`Vehicle` : `uuid`, `version`, `name`, `callsign`, `type`, `registration`, `vin`, `model`, `site`, `commissioned_at`, `mileage`, `mileage_at`, `mileage_source` (`prise_de_service`, `fin_de_service`, `plein`, `entretien`, `fiche`), `telematics_imei`, `status`, `deleted`, `updated_at`.

### Kilométrage (décision du porteur)

Chaque relevé du terrain (prise et fin de service, plein, entretien) passe par `Vehicle::recordMileage()` : le compteur ne recule jamais (un relevé plus bas reste dans sa saisie sans toucher le compteur) ; un saut de plus de 2 000 km (`MILEAGE_JUMP_ALERT_KM`) n'est pas appliqué et ouvre un événement « Relevé kilométrique à vérifier » (un seul ouvert par véhicule). Une correction dans la fiche s'applique telle quelle (source `fiche`). Date, source et auteur du dernier relevé : `mileage_at`, `mileage_source`, `mileage_by`.

Limite : 120 requêtes par minute.

## Étapes suivantes

- Écriture déléguée des champs partagés (`PATCH /api/v1/vehicles/{uuid}` avec la version attendue, 409 si elle a changé).
- Notifications signées vers Itineris (`vehicle.changed`, `vehicle.deleted`, `availability.requested`, `availability.cleared`).
- Demandes d'indisponibilité à la place des passages automatiques en indisponible, pour les véhicules reliés.
