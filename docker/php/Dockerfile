# Vulcain — image applicative PHP 8.3 (conforme au cahier des charges)
# Image de DÉVELOPPEMENT / TEST : auto-bootstrap (composer, clé, migrations).
# Une image de production durcie (non-root, code figé) sera fournie en phase déploiement.
FROM php:8.3-fpm-bookworm

# Dépendances système et extensions PHP.
# $PHPIZE_DEPS (autoconf, gcc, make…) est requis pour compiler l'extension redis via pecl.
RUN apt-get update && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        git unzip libpq-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd zip intl bcmath opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Argon2id est fourni par libsodium (intégré à PHP 8.3).

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html
COPY docker/php/php.ini /usr/local/etc/php/conf.d/vulcain.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
