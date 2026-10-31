#!/bin/sh
# Runs once in every php container (the app service, the optional reverb
# service, and any one-shot `docker compose run` container) before PID 1.
#
# Two things need preparing on a fresh host: the database, which is another
# container still busy creating itself, and the uploads volume, which starts
# empty while the image carries a snapshot of the files already published.
# Nothing in here overwrites existing data — the migrations only add, and the
# seeders only fill gaps — so a deploy can never roll back live content.
set -eu

cd /var/www/html

# storage/ and bootstrap/cache are volumes on the app service, so the skeleton
# directories Laravel needs have to exist here rather than rely on the image.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    storage/app/private \
    bootstrap/cache \
    /var/log/supervisor

# Wait for the database to accept connections. `depends_on: service_healthy`
# already does this under compose; the loop is for a bare `docker run`, where
# MySQL is somebody else's problem and a migration against a bootstrapping
# server would kill the container on its first attempt.
if [ "${DB_CONNECTION:-mysql}" != "sqlite" ] && [ "${DB_WAIT:-true}" != "false" ]; then
    echo "waiting for the database at ${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}"
    tries=0

    until DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3306}" php -r 'exit(@stream_socket_client(sprintf("tcp://%s:%d", getenv("DB_HOST"), getenv("DB_PORT")), $e, $s, 3) ? 0 : 1);'; do
        tries=$((tries + 1))

        if [ "$tries" -ge 40 ]; then
            echo "database still unreachable after 120s — check DB_HOST/DB_PORT" >&2
            exit 1
        fi

        sleep 3
    done
fi

# Uploads: Filament writes to storage/app/public, which is a volume, so a fresh
# host would show every image as a 404 until this copy runs.
if [ -d /seed/public ] && [ -z "$(ls -A storage/app/public 2>/dev/null)" ]; then
    echo "seeding storage/app/public from the image snapshot"
    cp -a /seed/public/. storage/app/public/
fi

# The schema travels with the code. This is a no-op once the database is
# current, and it is what makes an empty volume become a working site.
# Set MIGRATE_AT_BOOT=false on side services (reverb) that must not race the
# app container through the same migration list.
if [ "${MIGRATE_AT_BOOT:-true}" != "false" ]; then
    php artisan migrate --force

    # Languages, settings and the footer menu. Every seeder asks before it
    # writes — updateOrCreate on a code, a skip when a row exists — so this is
    # safe on every boot and is how a fresh install ends up configured without
    # a second command.
    if [ "${SEED_AT_BOOT:-true}" != "false" ]; then
        php artisan db:seed --force
    fi
fi

# public/storage is gitignored and the image never ships it. nginx serves
# /storage from the volume directly, but the app container still wants the link
# for Filament's own file previews.
[ -L public/storage ] || php artisan storage:link

# A production container boots from cached configuration; php-fpm, the queue
# worker and the scheduler all read the same files.
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
    php artisan view:cache
    # Filament components and Blade icons, in one call.
    php artisan filament:optimize
fi

# Named volumes mount as root:root while php-fpm workers, the queue and the
# scheduler run as www-data. After the artisan commands, so their own output
# files are writable by those processes too.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
