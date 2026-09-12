# syntax=docker/dockerfile:1.7

###############################################################################
# Ribs Recipes — production image
#
# Three build stages produce one small runtime image:
#
#   1. assets  — Vite build (also downloads and self-hosts the web fonts)
#   2. vendor  — Composer install, production dependencies only
#   3. runtime — FrankenPHP, which is a web server and PHP in a single process
#
# FrankenPHP is used instead of the usual nginx + php-fpm pair because this
# runs on a home server: one process, one container, no socket between them,
# and noticeably less memory for the same throughput.
###############################################################################

# --- 1. Front-end assets -----------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /build

# Dependencies first, so a content-only change does not reinstall them.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
COPY public ./public

# Downloads the web fonts and emits them into public/build, so the running
# site never makes a third-party request. This step needs network access.
RUN npm run build


# --- 2. PHP dependencies -----------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /build

# Dependencies resolve from the lock file alone, so this layer is reused
# until composer.lock actually changes.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# With the application present, build the optimised class map and run package
# discovery. Doing it here keeps Composer itself out of the runtime image.
COPY . .
RUN composer dump-autoload \
        --no-dev \
        --optimize \
        --classmap-authoritative \
        --no-interaction


# --- 3. Runtime --------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine AS runtime

LABEL org.opencontainers.image.title="Ribs Recipes" \
      org.opencontainers.image.description="A personal recipe collection" \
      org.opencontainers.image.source="https://github.com/"

WORKDIR /app

# The database lives on the persistent volume, never in the image layer.
# Setting it here means `docker run` behaves the same as Compose, and that the
# entrypoint and Laravel can never disagree about where the file is.
ENV DB_DATABASE=/app/storage/database/database.sqlite

# pdo_sqlite  — the database
# gd          — image processing (built with WebP and JPEG support)
# exif        — orientation, so portrait phone photos are not stored sideways
# intl        — punycode normalisation in the SSRF guard, and localisation
# zip, opcache, pcntl — Composer, performance, and the scheduler
RUN install-php-extensions \
        pdo_sqlite \
        gd \
        exif \
        intl \
        zip \
        opcache \
        pcntl \
    && apk add --no-cache sqlite tini

# Production PHP settings. Uploads are capped a little above the application's
# own limit so oversized files are refused by validation with a clear message
# rather than by the web server with a blank 413.
COPY docker/php.ini /usr/local/etc/php/conf.d/ribs.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

COPY . .
COPY --from=vendor /build/vendor ./vendor
COPY --from=vendor /build/bootstrap/cache ./bootstrap/cache
COPY --from=assets /build/public/build ./public/build

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/ribs-entrypoint
RUN chmod +x /usr/local/bin/ribs-entrypoint

# The health endpoint Laravel provides, used by Compose and by Traefik.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent --output /dev/null http://127.0.0.1/up || exit 1

EXPOSE 80

# tini reaps zombies and forwards signals, so `docker compose stop` is clean.
ENTRYPOINT ["/sbin/tini", "--", "ribs-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
