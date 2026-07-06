# Vulcain — image applicative PHP 8.3 (conforme au cahier des charges)
FROM php:8.3-fpm-bookworm

# Dépendances système et extensions PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd zip intl bcmath opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Argon2id est disponible via libsodium (intégré à PHP 8.3) — vérifié au runtime.

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Utilisateur non-root pour l'exécution
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app && useradd -u ${UID} -g app -m app

WORKDIR /var/www/html
COPY docker/php/php.ini /usr/local/etc/php/conf.d/vulcain.ini

USER app
EXPOSE 9000
CMD ["php-fpm"]
