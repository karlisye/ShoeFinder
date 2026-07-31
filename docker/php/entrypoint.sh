#!/bin/sh
set -eu

mkdir -p \
    storage/app/private/feed-imports \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
