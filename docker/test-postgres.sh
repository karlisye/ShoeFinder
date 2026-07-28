#!/bin/sh
set -eu

test_database="shoe_finder_test"

run_backend() {
    docker compose exec -T \
        -e DB_DATABASE="$test_database" \
        backend-php "$@"
}

cleanup() {
    docker compose exec -T postgres sh -c \
        "dropdb --if-exists --force -U \"\$POSTGRES_USER\" \"$test_database\""
}

trap cleanup EXIT INT TERM

cleanup
docker compose exec -T postgres sh -c \
    "createdb -U \"\$POSTGRES_USER\" \"$test_database\""
run_backend php artisan migrate:fresh --seed --force --no-interaction
run_backend php artisan db:seed --force --no-interaction
run_backend php artisan migrate:rollback --step=4 --force --no-interaction
run_backend php artisan tinker --execute="
    foreach ([
        'brands',
        'categories',
        'colours',
        'sizes',
        'retailers',
        'shoes',
        'shoe_variants',
        'shoe_images',
        'retailer_listings',
        'retailer_listing_sizes',
        'price_changes',
        'outbound_clicks',
    ] as \$table) {
        if (Illuminate\\Support\\Facades\\Schema::hasTable(\$table)) {
            throw new RuntimeException(\"Stage 1 rollback left table: {\$table}\");
        }
    }
"
run_backend php artisan migrate --seed --force --no-interaction
docker compose exec -T backend-php \
    ./vendor/bin/phpunit -c phpunit.postgres.xml
