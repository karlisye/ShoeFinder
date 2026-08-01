#!/bin/sh
set -eu

repository_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
compose_file=${BACKUP_COMPOSE_FILE:-$repository_root/compose.production.yaml}
environment_file=${BACKUP_ENV_FILE:-$repository_root/.env.production}
output_root=${BACKUP_OUTPUT_DIR:-$repository_root/backups}
tier=${BACKUP_TIER:-daily}
include_feeds=${BACKUP_INCLUDE_FEEDS:-false}
remote=${BACKUP_RCLONE_REMOTE:-}

usage() {
    printf '%s\n' "Usage: $0 [--compose-file PATH] [--env-file PATH] [--output-dir PATH] [--tier daily|weekly|monthly] [--include-feeds]"
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --compose-file)
            [ "$#" -ge 2 ] || { usage >&2; exit 2; }
            compose_file=$2
            shift 2
            ;;
        --env-file)
            [ "$#" -ge 2 ] || { usage >&2; exit 2; }
            environment_file=$2
            shift 2
            ;;
        --output-dir)
            [ "$#" -ge 2 ] || { usage >&2; exit 2; }
            output_root=$2
            shift 2
            ;;
        --tier)
            [ "$#" -ge 2 ] || { usage >&2; exit 2; }
            tier=$2
            shift 2
            ;;
        --include-feeds)
            include_feeds=true
            shift
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            printf '%s\n' "Unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

case "$tier" in
    daily)
        keep=${BACKUP_KEEP_DAILY:-7}
        ;;
    weekly)
        keep=${BACKUP_KEEP_WEEKLY:-4}
        ;;
    monthly)
        keep=${BACKUP_KEEP_MONTHLY:-6}
        ;;
    *)
        printf '%s\n' "Backup tier must be daily, weekly, or monthly." >&2
        exit 2
        ;;
esac

case "$keep" in
    ''|*[!0-9]*)
        printf '%s\n' "The retention count for $tier must be a positive integer." >&2
        exit 2
        ;;
esac

if [ "$keep" -lt 1 ]; then
    printf '%s\n' "The retention count for $tier must be at least 1." >&2
    exit 2
fi

if [ ! -f "$compose_file" ]; then
    printf '%s\n' "Compose file not found: $compose_file" >&2
    exit 1
fi

if [ ! -f "$environment_file" ]; then
    printf '%s\n' "Environment file not found: $environment_file" >&2
    exit 1
fi

command -v docker >/dev/null 2>&1 || {
    printf '%s\n' "Docker is required." >&2
    exit 1
}

compose() {
    docker compose \
        --env-file "$environment_file" \
        --file "$compose_file" \
        "$@"
}

write_checksums() {
    target_directory=$1
    shift

    if command -v sha256sum >/dev/null 2>&1; then
        (
            cd "$target_directory"
            sha256sum "$@" >SHA256SUMS
        )
        return
    fi

    if command -v shasum >/dev/null 2>&1; then
        (
            cd "$target_directory"
            shasum -a 256 "$@" >SHA256SUMS
        )
        return
    fi

    printf '%s\n' "sha256sum or shasum is required." >&2
    exit 1
}

prune_local_backups() {
    tier_directory=$1
    retained=$2
    position=0

    find "$tier_directory" \
        -mindepth 1 \
        -maxdepth 1 \
        -type d \
        -name 'shoe-finder-[0-9]*T[0-9]*Z' \
        -print | sort -r | while IFS= read -r backup_path; do
        position=$((position + 1))

        if [ "$position" -le "$retained" ]; then
            continue
        fi

        case "$backup_path" in
            "$tier_directory"/shoe-finder-*)
                rm -rf -- "$backup_path"
                ;;
            *)
                printf '%s\n' "Refusing to prune unexpected path: $backup_path" >&2
                exit 1
                ;;
        esac
    done
}

compose config --quiet

postgres_container=$(compose ps --quiet postgres)
backend_container=$(compose ps --quiet backend-php)

if [ -z "$postgres_container" ] || [ "$(docker inspect --format '{{.State.Running}}' "$postgres_container")" != "true" ]; then
    printf '%s\n' "The postgres service must be running before a backup starts." >&2
    exit 1
fi

if [ -z "$backend_container" ] || [ "$(docker inspect --format '{{.State.Running}}' "$backend_container")" != "true" ]; then
    printf '%s\n' "The backend-php service must be running before a backup starts." >&2
    exit 1
fi

umask 077
mkdir -p "$output_root/$tier"
lock_directory=$output_root/.backup.lock

if ! mkdir "$lock_directory" 2>/dev/null; then
    printf '%s\n' "Another backup appears to be running. Remove $lock_directory only after confirming that no backup process is active." >&2
    exit 1
fi

timestamp=$(date -u +%Y%m%dT%H%M%SZ)
backup_name=shoe-finder-$timestamp
tier_directory=$output_root/$tier
temporary_directory=$tier_directory/.incomplete-$backup_name-$$
backup_directory=$tier_directory/$backup_name

cleanup() {
    rm -rf -- "$temporary_directory"
    rmdir "$lock_directory" 2>/dev/null || true
}

trap cleanup EXIT INT TERM

if [ -e "$backup_directory" ]; then
    printf '%s\n' "Backup already exists: $backup_directory" >&2
    exit 1
fi

mkdir "$temporary_directory"

compose exec --no-TTY postgres sh -c '
    exec pg_dump \
        --username="$POSTGRES_USER" \
        --dbname="$POSTGRES_DB" \
        --format=custom \
        --compress=6 \
        --no-owner \
        --no-privileges
' >"$temporary_directory/database.dump"

compose exec --no-TTY backend-php \
    tar -czf - -C /var/www/html/storage/app/public . \
    >"$temporary_directory/media.tar.gz"

checksum_files="database.dump media.tar.gz metadata.txt"

if [ "$include_feeds" = "true" ]; then
    compose exec --no-TTY backend-php \
        tar -czf - -C /var/www/html/storage/app/private/feed-imports . \
        >"$temporary_directory/private-feeds.tar.gz"
    checksum_files="$checksum_files private-feeds.tar.gz"
fi

{
    printf '%s\n' "backup_format_version=1"
    printf '%s\n' "created_at_utc=$timestamp"
    printf '%s\n' "tier=$tier"
    printf '%s\n' "database_format=postgresql_custom"
    printf '%s\n' "media_format=tar_gzip"
    printf '%s\n' "private_feeds_included=$include_feeds"
    printf '%s\n' "redis_included=false"
    printf '%s\n' "secrets_included=false"
} >"$temporary_directory/metadata.txt"

write_checksums "$temporary_directory" $checksum_files
mv "$temporary_directory" "$backup_directory"

if [ -n "$remote" ]; then
    command -v rclone >/dev/null 2>&1 || {
        printf '%s\n' "BACKUP_RCLONE_REMOTE is set, but rclone is not installed." >&2
        exit 1
    }

    remote_target=${remote%/}/$tier/$backup_name
    rclone copy "$backup_directory" "$remote_target" --checksum
    rclone check "$backup_directory" "$remote_target" --one-way
fi

prune_local_backups "$tier_directory" "$keep"

trap - EXIT INT TERM
rmdir "$lock_directory"

printf '%s\n' "Backup created: $backup_directory"
