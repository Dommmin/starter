#!/bin/sh
set -eu

if [ "$(id -u)" = 0 ]; then
    usermod -o -u "${LOCAL_UID:-1000}" www-data
    groupmod -o -g "${LOCAL_GID:-1000}" www-data
    mkdir -p vendor node_modules storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs \
        storage/app/public bootstrap/cache /tmp/composer /tmp/npm
    for directory in vendor node_modules storage bootstrap/cache /tmp/composer /tmp/npm; do
        chown -R www-data:www-data "$directory"
    done
    # FPM's master opens Docker log descriptors before dropping worker privileges.
    if [ "${1:-}" = php-fpm ]; then
        exec "$@"
    fi
    exec gosu www-data "$0" "$@"
fi

export COMPOSER_HOME=/tmp/composer
export npm_config_cache=/tmp/npm
exec "$@"
