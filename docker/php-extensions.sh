#!/bin/sh
# PHP extensions for the Ruma v2 stack, shared by the builder (composer install
# and every `php artisan` call need them) and the php-fpm runtime image, so
# nothing is compiled twice.
#
# The official php images already ship ctype, curl, dom, fileinfo, iconv,
# json, mbstring, openssl, pcre, PDO, Phar, session, sqlite3, tokenizer, xml and
# zlib, so only the additions are listed here. pdo_sqlite is checked rather than
# assumed: the test suite runs on an in-memory SQLite database, and a base image
# that stops bundling it must not turn `php artisan test` into a container failure.
#
#   gd         image processing for Filament uploads
#   intl       Filament field formatting and locale handling
#   opcache    compiled bytecode; see docker/php.ini for the settings
#   pcntl      signals for queue:work and schedule:work
#   pdo_mysql  the content database — config/database.php and the `mysql` service
#   zip        composer dist packages
#   redis      the session/cache/queue driver config/database.php asks for
set -eu

apt-get update
apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev

docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
docker-php-ext-install -j"$(nproc)" \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    zip

if ! php -m | grep -qi '^pdo_sqlite$'; then
    docker-php-ext-install pdo_sqlite sqlite3
fi

# phpredis is a PECL extension, so it is not part of docker-php-ext-install.
# Without it every Redis call would fall back to predis, which is not installed.
pecl install redis
docker-php-ext-enable redis

rm -rf /var/lib/apt/lists/* /tmp/pear
