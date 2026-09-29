/**
 * Service worker Vulkain — PWA installable et résiliente hors-ligne.
 *
 * Stratégie volontairement prudente pour une app multi-utilisateurs :
 *  - on NE met JAMAIS en cache les réponses de navigation (pages authentifiées)
 *    ni les requêtes Inertia/API : pas de fuite de données entre utilisateurs
 *    sur un appareil partagé, jamais de page périmée servie ;
 *  - « cache-first » uniquement pour les assets buildés (/build/, empreinte de
 *    hash, immuables) et l'app shell statique (icônes, manifeste, page offline) ;
 *  - en cas de navigation hors-ligne, on sert une page offline de repli.
 */
const VERSION = 'v2';
const SHELL_CACHE = `vulkain-shell-${VERSION}`;
const ASSET_CACHE = `vulkain-assets-${VERSION}`;
const OFFLINE_URL = '/offline.html';

const SHELL_ASSETS = [
    OFFLINE_URL,
    '/site.webmanifest',
    '/favicon.svg',
    '/icon-192.png',
    '/icon-512.png',
    '/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((k) => k !== SHELL_CACHE && k !== ASSET_CACHE)
                    .map((k) => caches.delete(k)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Uniquement le GET même origine ; le reste part directement au réseau.
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // Navigations (pages) : réseau d'abord, repli page offline. Jamais mises en cache.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    const url = new URL(request.url);

    // Assets buildés (hash immuable) et app shell : cache d'abord, réseau en repli.
    const isBuildAsset = url.pathname.startsWith('/build/');
    const isShellAsset = SHELL_ASSETS.includes(url.pathname);

    if (isBuildAsset || isShellAsset) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        const bucket = isBuildAsset ? ASSET_CACHE : SHELL_CACHE;
                        caches.open(bucket).then((cache) => cache.put(request, copy));
                    }

                    return response;
                });
            }),
        );
    }

    // Tout le reste (XHR Inertia, images dynamiques, exports…) : réseau, non caché.
});
