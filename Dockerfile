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

# Stufe 2: Frontend-Assets mit Webpack Encore bauen
FROM node:22-alpine AS assets

WORKDIR /app

# package.json referenziert "@symfony/ux-live-component" über einen file:-Pfad
# im Vendor-Verzeichnis. Dieses muss deshalb vor "npm ci" vorhanden sein.
COPY --from=composer /app/vendor/symfony/ux-live-component ./vendor/symfony/ux-live-component

COPY package.json package-lock.json ./
RUN npm ci

COPY webpack.config.js ./
COPY assets ./assets

# Erzeugt public/build/ inklusive entrypoints.json und gehashter Dateinamen
RUN npm run build

# Stufe 3: PHP-FPM Basis-Image
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

# cgi-fcgi wird für den Healthcheck des FPM-Pools benötigt
RUN apk add --no-cache fcgi

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

COPY docker/php/opcache-dev.ini /usr/local/etc/php/conf.d/zzz-opcache-dev.ini

RUN composer install \
    --no-interaction \
    --no-progress

USER www-data

# Produktions-Target: Assets einbinden, Cache aufwärmen
FROM base AS production

# Produktions-Overrides. Die Dateinamen sind so gewählt, dass sie alphabetisch
# nach app.ini bzw. zz-docker.conf geladen werden und diese überschreiben.
COPY docker/php/php-prod.ini  /usr/local/etc/php/conf.d/zzz-app-prod.ini
COPY docker/php/fpm-prod.conf /usr/local/etc/php-fpm.d/zzz-prod.conf

# Gebaute Frontend-Assets aus der Node-Stufe übernehmen. Ohne diesen Schritt
# fehlt public/build/entrypoints.json und jedes Template wirft eine Exception.
COPY --from=assets /app/public/build ./public/build

# Echte Umgebungsvariablen haben in Symfony Vorrang vor der .env-Datei
ENV APP_ENV=prod \
    APP_DEBUG=0

# Cache vorwärmen, Rechte für den FPM-Benutzer setzen, Composer entfernen
RUN php bin/console cache:warmup \
    && chown -R www-data:www-data var \
    && rm -f /usr/bin/composer

USER www-data

# Stufe 4: Nginx mit dem fertigen Web-Root der Anwendung
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf

# Nur public/ wird benötigt: statische Dateien liefert nginx selbst aus,
# PHP-Anfragen gehen per FastCGI an den php-Container.
COPY --from=production /var/www/html/public /var/www/html/public
