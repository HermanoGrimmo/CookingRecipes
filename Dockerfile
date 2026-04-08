# Stufe 1: Composer-Abhängigkeiten installieren
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --ignore-platform-reqs

# Stufe 2: PHP-FPM Basis-Image
FROM php:8.4-fpm-alpine AS base

# install-php-extensions für schnelle, vorkompilierte Extension-Installation
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_pgsql \
        intl \
        zip \
        gd \
        opcache \
        bcmath

# OPcache und allgemeine PHP-Einstellungen
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini     /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# Composer-Binary aus dem ersten Build-Schritt übernehmen
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Vendor-Verzeichnis aus dem Composer-Schritt übernehmen
COPY --from=composer /app/vendor ./vendor

# Anwendungscode kopieren
COPY . .

# Symfony-Cache und Logs vorbereiten
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

# Entwicklungs-Target: Dev-Dependencies nachinstallieren
FROM base AS development

RUN composer install \
    --no-interaction \
    --no-progress

USER www-data

# Produktions-Target: Cache aufwärmen
FROM base AS production

RUN APP_ENV=prod php bin/console cache:warmup

USER www-data
