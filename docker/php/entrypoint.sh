#!/bin/sh
# Point d'entrée de l'environnement de test local.
# Le service "app" (VULCAIN_BOOTSTRAP=1) prépare l'application ; les autres
# services PHP (queue) attendent simplement que la préparation soit terminée.
set -e
cd /var/www/html

if [ "$VULCAIN_BOOTSTRAP" = "1" ]; then
    # 1. Fichier d'environnement
    if [ ! -f .env ]; then
        echo "📝 Création du .env depuis .env.example"
        cp .env.example .env
    fi

    # 2. Dépendances PHP (autoloader optimisé)
    if [ ! -f vendor/autoload.php ]; then
        echo "📦 Installation des dépendances Composer (première exécution)…"
        composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
    fi

    # 3. Droits d'écriture (bind mount)
    chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

    # 4. Clé applicative
    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "🔑 Génération de la clé applicative"
        php artisan key:generate --force
    fi

    # 5. Migrations + données de démonstration (idempotent)
    echo "🗄️  Migrations et données de démonstration…"
    php artisan migrate --force --seed || php artisan migrate --force

    # 5 bis. Mise en cache (config, routes, vues, événements) — fluidité.
    # Reconstruit à chaque démarrage : toujours cohérent avec le code monté.
    echo "⚡ Optimisation (cache config/routes/vues)…"
    php artisan optimize >/dev/null 2>&1 || php artisan optimize:clear >/dev/null 2>&1 || true

    # 6. Attendre la construction des assets front (service "assets"), max ~2 min
    echo "🎨 Attente de la construction des assets front…"
    i=0
    while [ ! -f public/build/manifest.json ] && [ "$i" -lt 60 ]; do
        sleep 2
        i=$((i + 1))
    done

    echo "✅ Vulcain est prêt : http://demo.localhost:8080"
else
    # Services secondaires : attendre que "app" ait préparé vendor + .env
    echo "⏳ Attente de la préparation de l'application…"
    until [ -f vendor/autoload.php ] && [ -f .env ]; do
        sleep 2
    done
fi

exec "$@"
