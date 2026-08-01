#!/bin/sh
set -eu

repository_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
project_name=shoe_finder_production_verify
environment_file=$(mktemp "${TMPDIR:-/tmp}/shoe-finder-production.XXXXXX")
backup_root=$(mktemp -d "${TMPDIR:-/tmp}/shoe-finder-backups.XXXXXX")
services="postgres redis backend-php backend-worker backend-web frontend proxy"

compose() {
    docker compose \
        --env-file "$environment_file" \
        --project-name "$project_name" \
        --file "$repository_root/compose.production.yaml" \
        "$@"
}

cleanup() {
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -f "$environment_file"
    rm -rf -- "$backup_root"
}

wait_for_health() {
    attempt=0

    while [ "$attempt" -lt 60 ]; do
        all_healthy=true

        for service in $services; do
            container_id=$(compose ps --quiet "$service")

            if [ -z "$container_id" ]; then
                all_healthy=false
                continue
            fi

            container_status=$(
                docker inspect \
                    --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' \
                    "$container_id"
            )

            if [ "$container_status" != "healthy" ]; then
                all_healthy=false
            fi
        done

        if [ "$all_healthy" = "true" ]; then
            return 0
        fi

        attempt=$((attempt + 1))
        sleep 2
    done

    compose ps
    return 1
}

trap cleanup EXIT INT TERM

app_key="base64:$(dd if=/dev/urandom bs=32 count=1 2>/dev/null | base64 | tr -d '\n')"

umask 077
{
    printf '%s\n' "COMPOSE_PROJECT_NAME=$project_name"
    printf '%s\n' "APP_PORT=18080"
    printf '%s\n' "APP_URL=http://localhost:18080"
    printf '%s\n' "APP_KEY=$app_key"
    printf '%s\n' "FILAMENT_ADMIN_EMAIL=stage8-production@example.test"
    printf '%s\n' "POSTGRES_DB=shoe_finder_production_verify"
    printf '%s\n' "POSTGRES_USER=shoe_finder_production_verify"
    printf '%s\n' "POSTGRES_PASSWORD=stage8-production-verification-only"
    printf '%s\n' "OFFER_STALE_AFTER_HOURS=168"
} >"$environment_file"

compose config --quiet
compose build
compose up --detach
wait_for_health

compose exec --no-TTY backend-php php artisan tinker --execute='
    if (Illuminate\Support\Facades\Schema::hasTable("migrations")) {
        throw new RuntimeException("Production startup ran migrations automatically.");
    }
'

compose exec --no-TTY backend-php php artisan migrate --force --no-interaction
compose exec --no-TTY backend-php php artisan db:seed --force --no-interaction

compose exec --no-TTY backend-php php artisan tinker --execute='
    if (! Illuminate\Support\Facades\Schema::hasTable("migrations")) {
        throw new RuntimeException("Explicit migrations did not create the migration table.");
    }

    if (App\Models\Size::query()->count() !== 79) {
        throw new RuntimeException("EU size reference data is incomplete.");
    }

    if (App\Models\Colour::query()->count() !== 13) {
        throw new RuntimeException("Colour reference data is incomplete.");
    }
'

compose exec --no-TTY backend-php php artisan tinker --execute='
    Illuminate\Support\Facades\Storage::disk("public")->put(
        "production-verification/backup-probe.txt",
        "backup-media-probe",
    );
'

"$repository_root/docker/backup.sh" \
    --compose-file "$repository_root/compose.production.yaml" \
    --env-file "$environment_file" \
    --output-dir "$backup_root" \
    --tier daily
backup_set=$(
    find "$backup_root/daily" \
        -mindepth 1 \
        -maxdepth 1 \
        -type d \
        -name 'shoe-finder-*' \
        -print | sort -r | head -n 1
)
"$repository_root/docker/verify-backup.sh" "$backup_set"
tar -tzf "$backup_set/media.tar.gz" |
    grep -q '^\./production-verification/backup-probe.txt$'

compose exec --no-TTY backend-php cp \
    tests/Fixtures/ProductFeeds/clean/sole-market.csv \
    storage/app/private/feed-imports/queue-probe.csv
queue_probe_id=$(
    compose exec --no-TTY backend-php php artisan tinker --execute='
        $retailer = App\Models\Retailer::create([
            "name" => "Queue probe",
            "slug" => "sole-market",
            "website_url" => "https://queue-probe.example",
        ]);
        $import = App\Models\FeedImport::create([
            "retailer_id" => $retailer->id,
            "original_filename" => "queue-probe.csv",
            "stored_path" => "feed-imports/queue-probe.csv",
            "format" => "csv",
            "status" => App\Models\FeedImport::STATUS_UPLOADED,
        ]);
        app(App\Domain\Feeds\FeedImportQueue::class)->preview($import);
        echo $import->id;
    '
)

queue_attempt=0
while [ "$queue_attempt" -lt 30 ]; do
    queue_status=$(
        compose exec --no-TTY backend-php php artisan tinker --execute="
            echo App\\Models\\FeedImport::findOrFail($queue_probe_id)->status;
        "
    )

    if [ "$queue_status" = "ready" ]; then
        break
    fi

    queue_attempt=$((queue_attempt + 1))
    sleep 1
done

if [ "$queue_status" != "ready" ]; then
    printf '%s\n' "The production imports worker did not process its queue." >&2
    exit 1
fi

compose exec --no-TTY backend-php php artisan tinker --execute='
    Illuminate\Support\Facades\Cache::put("production-verification", "persistent", 120);
'

if [ "$(compose exec --no-TTY frontend id -u)" = "0" ]; then
    printf '%s\n' "The production frontend must not run as root." >&2
    exit 1
fi

compose exec --no-TTY backend-php php -r '
    if (ini_get("display_errors") !== "" || ini_get("opcache.validate_timestamps") !== "0") {
        exit(1);
    }
'

compose exec --no-TTY proxy wget -q -O /dev/null http://127.0.0.1/
compose exec --no-TTY proxy wget -q -O /dev/null http://127.0.0.1/en/
compose exec --no-TTY proxy wget -q -O /dev/null http://127.0.0.1/catalogue
compose exec --no-TTY proxy wget -q -O /dev/null http://127.0.0.1/admin
compose exec --no-TTY proxy wget -q -O - http://127.0.0.1/up |
    grep -q '"database":"connected"'
compose exec --no-TTY proxy wget -q -O - http://127.0.0.1/up |
    grep -q '"redis":"connected"'
response_headers=$(compose exec --no-TTY proxy wget -S -O /dev/null http://127.0.0.1/ 2>&1)
printf '%s\n' "$response_headers" | grep -qi 'X-Content-Type-Options: nosniff'
printf '%s\n' "$response_headers" | grep -qi 'X-Frame-Options: SAMEORIGIN'
printf '%s\n' "$response_headers" | grep -qi 'Referrer-Policy: strict-origin-when-cross-origin'
printf '%s\n' "$response_headers" | grep -qi 'Permissions-Policy: camera=(), geolocation=(), microphone=()'

compose restart
wait_for_health
compose exec --no-TTY proxy wget -q -O /dev/null http://127.0.0.1/
compose exec --no-TTY proxy wget -q -O - http://127.0.0.1/up |
    grep -q '"database":"connected"'
compose exec --no-TTY proxy wget -q -O - http://127.0.0.1/up |
    grep -q '"redis":"connected"'
compose exec --no-TTY backend-php php artisan tinker --execute='
    if (Illuminate\Support\Facades\Cache::get("production-verification") !== "persistent") {
        throw new RuntimeException("Redis data did not survive container restart.");
    }
'

printf '%s\n' "Production verification passed."
