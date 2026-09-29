<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Vulkain') }}</title>

    {{-- Favicon Vulkain (SVG moderne + repli ICO/PNG + icône installable) --}}
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=3">
    <link rel="manifest" href="/site.webmanifest?v=3">
    <meta name="theme-color" content="#12161C">

    {{-- PWA : installable sur iOS (Ajouter à l'écran d'accueil) --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Vulkain">
    <meta name="mobile-web-app-capable" content="yes">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">
    @inertia

    {{-- Enregistrement du service worker (PWA hors-ligne). Ignoré sans HTTPS. --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                // Recharge une fois quand une nouvelle version prend le contrôle
                // (évite un bundle périmé en cache sur les appareils installés).
                var refreshing = false;
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.addEventListener('controllerchange', function () {
                        if (refreshing) return;
                        refreshing = true;
                        window.location.reload();
                    });
                }
                navigator.serviceWorker.register('/sw.js').then(function (reg) {
                    // Vérifie une mise à jour à chaque chargement.
                    reg.update().catch(function () {});
                }).catch(function () { /* silencieux */ });
            });
        }
    </script>
</body>
</html>
