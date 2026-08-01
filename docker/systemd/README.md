# Production backups

## Backup contents

`docker/backup.sh` creates one independent backup set containing:

- A PostgreSQL custom-format logical dump.
- A compressed public-media archive.
- Non-secret metadata and SHA-256 checksums.
- An optional private-feed archive when `--include-feeds` is used.

Redis, `.env.production`, `APP_KEY`, credentials, and administrator recovery codes are excluded. Store production secrets separately in an encrypted password manager or secrets store.

## Manual backup and verification

The PostgreSQL and backend PHP services must be running.

```sh
./docker/backup.sh --tier daily
```

Verify the completed set:

```sh
./docker/verify-backup.sh /var/backups/shoe-finder/daily/shoe-finder-YYYYMMDDTHHMMSSZ
```

The verifier checks file integrity, reads the archives, and restores PostgreSQL into a temporary isolated container. It never connects to the live database.

## Server setup

1. Deploy the repository to `/opt/shoe-finder`.
2. Copy `backup.env.example` to `/etc/shoe-finder/backup.env`.
3. Set its owner to `root:root` and mode to `0600`.
4. Copy `shoe-finder-backup@.service` and the three timer files to `/etc/systemd/system/`.
5. Reload systemd.
6. Enable the daily, weekly, and monthly timers.
7. Run one backup service manually and verify its output.

```sh
sudo install -d -m 0700 /etc/shoe-finder /var/backups/shoe-finder
sudo install -m 0600 docker/systemd/backup.env.example /etc/shoe-finder/backup.env
sudo install -m 0644 docker/systemd/shoe-finder-backup@.service /etc/systemd/system/
sudo install -m 0644 docker/systemd/shoe-finder-backup-daily.timer /etc/systemd/system/
sudo install -m 0644 docker/systemd/shoe-finder-backup-weekly.timer /etc/systemd/system/
sudo install -m 0644 docker/systemd/shoe-finder-backup-monthly.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now \
  shoe-finder-backup-daily.timer \
  shoe-finder-backup-weekly.timer \
  shoe-finder-backup-monthly.timer
sudo systemctl start shoe-finder-backup@daily.service
```

Inspect timer and backup status:

```sh
systemctl list-timers 'shoe-finder-backup-*'
systemctl status shoe-finder-backup@daily.service
journalctl -u shoe-finder-backup@daily.service
```

## Retention and remote storage

Local retention defaults to 7 daily, 4 weekly, and 6 monthly sets. Override these values in `/etc/shoe-finder/backup.env`.

Set `BACKUP_RCLONE_REMOTE` to upload completed backups to private remote storage:

```text
BACKUP_RCLONE_REMOTE=hetzner:shoe-finder-backups/production
```

Install and configure `rclone` on the server. Keep its S3-compatible credentials outside the repository. Configure object-storage versioning and remote lifecycle rules separately. Local pruning does not delete remote backups.

## Recovery

Do not restore into a live database while the application or import worker can write to it.

1. Stop public traffic and application writes.
2. Preserve the existing database and media volumes.
3. Verify the selected backup.
4. Create fresh PostgreSQL and media volumes.
5. Restore `database.dump` with `pg_restore --exit-on-error --no-owner --no-privileges`.
6. Extract `media.tar.gz` into the fresh media volume.
7. Start the application with the original `.env.production` and `APP_KEY`.
8. Check `/up`, administrator sign-in, catalogue records, images, and outbound redirects.
9. Switch traffic only after the restored application passes these checks.

The exact restore commands depend on whether recovery uses the existing server, a new Compose project, or a replacement VPS. Keep restoration manual so it cannot overwrite production data accidentally.
