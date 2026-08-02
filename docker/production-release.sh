#!/bin/sh
set -eu

repository_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
compose_file=${PRODUCTION_COMPOSE_FILE:-$repository_root/compose.production.yaml}
environment_file=${PRODUCTION_ENV_FILE:-$repository_root/.env.production}
image_environment_file=${PRODUCTION_IMAGE_ENV_FILE:-$repository_root/.env.images}

usage() {
    printf '%s\n' "Usage: $0 deploy|migrate|health"
}

if [ "$#" -ne 1 ]; then
    usage >&2
    exit 2
fi

for required_file in "$compose_file" "$environment_file" "$image_environment_file"; do
    if [ ! -f "$required_file" ]; then
        printf '%s\n' "Required deployment file not found: $required_file" >&2
        exit 1
    fi
done

command -v docker >/dev/null 2>&1 || {
    printf '%s\n' "Docker is required." >&2
    exit 1
}

compose() {
    docker compose \
        --env-file "$environment_file" \
        --env-file "$image_environment_file" \
        --file "$compose_file" \
        "$@"
}

wait_for_health() {
    services=$(compose config --services)
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

case "$1" in
    deploy)
        compose config --quiet
        compose pull
        compose up --detach --no-build --remove-orphans
        ;;
    migrate)
        compose exec --no-TTY backend-php \
            php artisan migrate --force --no-interaction
        compose exec --no-TTY backend-php \
            php artisan db:seed --force --no-interaction
        ;;
    health)
        wait_for_health
        health_response=$(
            compose exec --no-TTY proxy \
                wget -q -O - http://127.0.0.1/up
        )
        printf '%s\n' "$health_response"
        printf '%s\n' "$health_response" | grep -q '"status":"ok"'
        printf '%s\n' "$health_response" | grep -q '"database":"connected"'
        printf '%s\n' "$health_response" | grep -q '"redis":"connected"'
        ;;
    *)
        usage >&2
        exit 2
        ;;
esac
