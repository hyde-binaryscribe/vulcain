# Documentation de sécurité — Vulcain

Document vivant. Recense les mesures de sécurité en place, par domaine.

## Multi-tenant (isolation des données)
- Cloisonnement par `organisation_id` sur chaque modèle métier (trait `BelongsToOrganisation`).
- `OrganisationScope` filtre automatiquement toutes les lectures sur l'organisation courante.
- `organisation_id` **jamais** exposé au mass assignment ; renseigné par le contexte serveur.
- Lecture d'un modèle cloisonné sans contexte en requête HTTP → exception (pas de fuite silencieuse).
- Résolution du tenant côté serveur (sous-domaine) ; organisation suspendue → 403, inconnue → 404.
- Uniformité e-mail : unique **par organisation**, pas globalement.

## Hachage des mots de passe
- **Argon2id** par défaut (`config/hashing.php`), paramètres via `.env` (mémoire/threads/temps).
- Cast `hashed` sur `User::password` ; rehash à la connexion activé.

## Sessions & cookies (socle en place, durcissement en Phase 1.2)
- Sessions stockées en base (permet la révocation).
- `.env.example` : `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE` (true en prod HTTPS),
  `SESSION_SAME_SITE=lax`.

## Secrets & configuration
- `.env` hors dépôt Git ; `.env.example` sans aucun secret.
- `APP_DEBUG=false` et masquage des erreurs à activer en production.

## En-têtes HTTP
- Nginx : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` (CSP affinée à venir).

## À venir (phases suivantes)
- Rate limiting / blocage après échecs, reset par jeton haché à usage unique (Phase 1.2).
- RBAC serveur + policies anti-IDOR par ressource (Phase 1.3).
- Fichiers privés hors webroot + URL signées (phase fichiers).
- Journal d'audit append-only (phase audit).
