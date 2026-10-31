# syntax=docker/dockerfile:1
#
# Ruma Organic v2 — Blade + Livewire + Filament stack.
#
# Six stages, ordered so the slow work is cached behind the frequent work:
#
#   php-base       php-fpm + compiled extensions. The builder and the runtime
#                  both inherit it, so nothing is ever compiled twice.
#   node-tools     node + npm binaries, lifted into the builder only.
#   composer-tools composer binary, lifted into the builder only.
#   builder        composer install, npm ci, `php artisan` asset publishing and
#                  `npm run build`. Produces the tree the runtime stages copy.
#   app            php-fpm + supervisord (queue worker, scheduler). Serves
#                  nothing directly; nginx talks to it over FastCGI.
#   web            nginx, serving public/ and the shared uploads volume.
#
# Versions are pinned so a rebuild next month produces the same image:
# override with --build-arg when you deliberately move up.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22
ARG COMPOSER_VERSION=2
ARG NGINX_VERSION=alpine

# ---------------------------------------------------------------------------
# php-base
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS php-base

COPY docker/php-extensions.sh /tmp/php-extensions.sh
RUN bash /tmp/php-extensions.sh

# ---------------------------------------------------------------------------
# node-tools / composer-tools — toolchains, used only as COPY --from sources
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS node-tools

FROM composer:${COMPOSER_VERSION} AS composer-tools

# ---------------------------------------------------------------------------
# builder
# ---------------------------------------------------------------------------
FROM php-base AS builder

ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http

# Node lives under /usr/local in the node image; the php image keeps its own
# binaries there too and the two sets do not overlap (node, npm, npx, corepack).
COPY --from=node-tools /usr/local /usr/local
COPY --from=composer-tools /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, keyed on the lockfiles, so editing a Blade template does
# not re-run `npm ci` or `composer install`.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Composer scripts were skipped above (--no-scripts) because they shell out to
# `php artisan`, which needs the application and an environment. Both arrive
# with this copy, so the artisan steps run here instead.
COPY . .

# `key:generate`, `package:discover` and `filament:assets` all need a bootable
# app. The build .env is throwaway: the real configuration is injected by the
# container's environment at runtime, and only the VITE_ values below are baked
# into the built assets (Vite inlines them at build time, which is why turning
# on realtime chat means rebuilding the image, not just restarting it).
RUN cp .env.example .env \
    && php artisan key:generate --ansi \
    && printf 'VITE_REVERB_APP_KEY=%s\nVITE_REVERB_HOST=%s\nVITE_REVERB_PORT=%s\nVITE_REVERB_SCHEME=%s\n' \
        "$VITE_REVERB_APP_KEY" "$VITE_REVERB_HOST" "$VITE_REVERB_PORT" "$VITE_REVERB_SCHEME" >> .env \
    && php artisan package:discover --ansi \
    && php artisan filament:assets --ansi \
    && npm run build \
    && rm -f .env public/hot \
    && rm -rf node_modules .npm

# The published uploads, for first boot on a fresh host. The schema and the
# content come from the migrations and the MySQL volume, but the image files
# live on a volume that starts empty, so every upload would 404 without this.
# Kept outside the app tree so nothing can accidentally serve or overwrite it.
RUN mkdir -p /seed \
    && (cp -a storage/app/public /seed/public || true)

# ---------------------------------------------------------------------------
# app — php-fpm, queue worker, scheduler
# ---------------------------------------------------------------------------
FROM php-base AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends supervisor procps \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ruma.ini
# Overwrite the image's pool, not add to it: two files both declaring [www]
# makes php-fpm refuse to start.
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
# Replace the image's own master config; our file is self-contained.
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/ruma-entrypoint
RUN chmod +x /usr/local/bin/ruma-entrypoint

WORKDIR /var/www/html

# Defaults so the image also runs standalone (`docker run`), without compose.
# docker-compose.yml overrides what actually matters per host.
ENV APP_ENV=production \
    APP_DEBUG=false \
    QUEUE_CONNECTION=redis \
    SUPERVISOR_QUEUE_AUTOSTART=true \
    SUPERVISOR_SCHEDULER_AUTOSTART=true

COPY --from=builder /var/www/html ./
COPY --from=builder /seed /seed

ENTRYPOINT ["/usr/local/bin/ruma-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

EXPOSE 9000

# ---------------------------------------------------------------------------
# web — nginx
# ---------------------------------------------------------------------------
FROM nginx:${NGINX_VERSION} AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
# Only the public document root belongs here. `public/storage` is deliberately
# absent: nginx maps /storage to the uploads volume with an alias instead.
COPY --from=builder /var/www/html/public /var/www/html/public

EXPOSE 80
