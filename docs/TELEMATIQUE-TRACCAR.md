# Télématique — mise en service (Traccar → Vulkain)

Architecture : le boîtier **FMC003** envoie ses trames (Codec 8E, TCP) à
**Traccar** sur un **VPS** ; Traccar décode et **transfère** chaque position au
webhook de Vulkain (`/ingest/traccar/{token}`). Vulkain ne reçoit que du HTTP —
aucun port à ouvrir sur l'hébergement applicatif (Plesk reste chrooté).

```
FMC003 (SIM Orange, 4G) ──TCP:5027──► Traccar (VPS) ──HTTPS forward──► Vulkain
```

## 0. Prérequis

- Un **VPS** Linux (Debian/Ubuntu) avec IP publique et le **port 5027/TCP** ouvert
  (protocole Teltonika dans Traccar). Un petit VPS (1 vCPU / 1–2 Go) suffit.
- La **SIM Orange Multi-SIM** activée et insérée dans le FMC003.
- Un **PC Windows + câble USB** + **Teltonika Configurator** (gratuit, site Teltonika).
- Le **jeton d'ingestion** Vulkain (voir §4).

## 1. Installer Traccar sur le VPS

```bash
# Debian/Ubuntu (exécuter en root)
apt update && apt install -y openjdk-17-jre-headless unzip
cd /tmp
curl -LO https://www.traccar.org/download/traccar-linux-64-latest.zip
unzip traccar-linux-64-latest.zip
./traccar.run
systemctl enable --now traccar
```

- Interface web : `http://<IP_VPS>:8082` (créer le compte admin à la 1ʳᵉ visite).
- Le port **5027** écoute les boîtiers Teltonika dès le démarrage.
- Conseil : mettre un reverse-proxy HTTPS (nginx/Caddy) devant le 8082, et
  restreindre l'accès web ; **laisser 5027 ouvert** pour les boîtiers.

## 2. Configurer le FMC003 (Teltonika Configurator)

Brancher le boîtier en USB, « Connect ».

- **GPRS / APN** : APN de la SIM Orange. Selon l'offre M2M c'est en général
  `orange` ou `orange.m2m` (user/pass vides). ⚠️ **À confirmer avec Orange** pour
  ta Multi-SIM — une mauvaise APN = pas de data.
- **GPRS / Server settings** : `Domain` = IP publique du VPS, `Port` = **5027**,
  `Protocol` = **TCP**.
- **System / Data Acquisition** : régler la fréquence d'envoi (ex. toutes les
  30 s en roulant, plus espacé à l'arrêt).
- **I/O** : activer les éléments OBD qu'on exploite :
  - **AVL ID 30 — Number of DTC** (→ `faultCount`),
  - **AVL ID 281 — Fault Codes** (→ `dtcs`),
  - plus vitesse / RPM / niveau carburant si souhaité.
- « Save to device ». Le boîtier doit apparaître « connecté » dans Traccar sous
  quelques minutes (sinon : vérifier APN, couverture, IMEI).

Dans Traccar : ajouter le device avec son **IMEI** (ou il s'enregistre seul au
premier contact selon la config).

## 3. Activer le transfert Traccar → Vulkain

Éditer `/opt/traccar/conf/traccar.xml`, ajouter dans `<properties>` :

```xml
<entry key='forward.enable'>true</entry>
<entry key='forward.type'>json</entry>
<entry key='forward.url'>https://app.vulkain.eu/ingest/traccar/REMPLACER_PAR_LE_JETON</entry>
```

Puis `systemctl restart traccar`. Traccar POSTe alors un JSON
`{ "device": { "uniqueId": "<IMEI>" }, "position": { "latitude":…, "longitude":…,
"speed":…, "attributes": { "faultCount":…, "dtcs":"…" } } }` à chaque position.

## 4. Côté Vulkain

1. **Définir le jeton** dans le `.env` (Plesk → variables d'environnement) :
   `TELEMATICS_INGEST_TOKEN=une-longue-chaine-aléatoire` (la même que dans
   `forward.url` ci-dessus). Jeton vide = endpoint désactivé (404).
2. Après déploiement : `php artisan migrate --force` puis `optimize:clear`.
3. **Renseigner l'IMEI** du boîtier sur la fiche du véhicule
   (Véhicules → éditer → « IMEI boîtier télématique »).
4. Ouvrir la fiche véhicule → onglet **Télématique** : la dernière position, la
   trace récente (carte OpenStreetMap) et l'état OBD y apparaissent dès la
   première trame reçue.

## 5. Valider le pilote (1 boîtier, 1 véhicule)

- [ ] Le boîtier est « connecté » dans Traccar, positions visibles.
- [ ] L'onglet **Télématique** de Vulkain montre la position (horodatage récent).
- [ ] Débrancher un capteur non critique (ou lire un code existant) → vérifier que
      `faultCount`/`dtcs` remontent et qu'un **Événement « Défaut moteur (OBD) »**
      se crée automatiquement (dashboard Événements).
- [ ] Rebrancher / effacer : l'alerte se clôt au cycle suivant.

Si `faultCount`/`dtcs` ne remontent pas sur ce véhicule : c'est la limite OBD
connue (dépend du véhicule) — la géoloc marche, le code détaillé non. Décider au
cas par cas avant d'équiper toute la flotte.

## 6. RGPD (à cadrer avant généralisation)

La géoloc des véhicules implique indirectement les agents. À prévoir : toggle
d'activation par organisation, durée de rétention des positions, information des
salariés. (Non encore implémenté côté Vulkain — à faire avant déploiement large.)
