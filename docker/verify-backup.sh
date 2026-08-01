#!/bin/sh
set -eu

usage() {
    printf '%s\n' "Usage: $0 BACKUP_DIRECTORY"
}

if [ "$#" -ne 1 ]; then
    usage >&2
    exit 2
fi

backup_directory=$1
database_dump=$backup_directory/database.dump
media_archive=$backup_directory/media.tar.gz
checksums=$backup_directory/SHA256SUMS
metadata=$backup_directory/metadata.txt
postgres_image=${BACKUP_POSTGRES_IMAGE:-postgres:18-alpine}

for required_file in "$database_dump" "$media_archive" "$checksums" "$metadata"; do
    if [ ! -f "$required_file" ]; then
        printf '%s\n' "Required backup file not found: $required_file" >&2
        exit 1
    fi
done

grep -q '^backup_format_version=1$' "$metadata" || {
    printf '%s\n' "Unsupported or missing backup format version." >&2
    exit 1
}

if command -v sha256sum >/dev/null 2>&1; then
    (cd "$backup_directory" && sha256sum -c SHA256SUMS)
elif command -v shasum >/dev/null 2>&1; then
    (cd "$backup_directory" && shasum -a 256 -c SHA256SUMS)
else
    printf '%s\n' "sha256sum or shasum is required." >&2
    exit 1
fi

tar -tzf "$media_archive" >/dev/null

if [ -f "$backup_directory/private-feeds.tar.gz" ]; then
    tar -tzf "$backup_directory/private-feeds.tar.gz" >/dev/null
fi

command -v docker >/dev/null 2>&1 || {
    printf '%s\n' "Docker is required." >&2
    exit 1
}

container_name=shoe-finder-backup-verify-$(date -u +%Y%m%dT%H%M%SZ)-$$

cleanup() {
    docker rm --force "$container_name" >/dev/null 2>&1 || true
}

trap cleanup EXIT INT TERM

docker run \
    --detach \
    --name "$container_name" \
    --tmpfs /var/lib/postgresql:rw \
    --env POSTGRES_DB=backup_verifier \
    --env POSTGRES_USER=backup_verifier \
    --env POSTGRES_PASSWORD=backup-verification-only \
    "$postgres_image" >/dev/null

attempt=0
while [ "$attempt" -lt 60 ]; do
    if docker exec "$container_name" pg_isready \
        --username=backup_verifier \
        --dbname=backup_verifier >/dev/null 2>&1; then
        break
    fi

    attempt=$((attempt + 1))
    sleep 1
done

if [ "$attempt" -eq 60 ]; then
    docker logs "$container_name" >&2
    printf '%s\n' "The isolated verification database did not become ready." >&2
    exit 1
fi

docker exec -i "$container_name" pg_restore \
    --username=backup_verifier \
    --dbname=backup_verifier \
    --exit-on-error \
    --no-owner \
    --no-privileges \
    <"$database_dump"

table_count=$(
    docker exec "$container_name" psql \
        --username=backup_verifier \
        --dbname=backup_verifier \
        --tuples-only \
        --no-align \
        --command="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public';"
)

case "$table_count" in
    ''|0|*[!0-9]*)
        printf '%s\n' "The restored database contains no public tables." >&2
        exit 1
        ;;
esac

trap - EXIT INT TERM
cleanup

printf '%s\n' "Backup verification passed with $table_count restored public tables."
