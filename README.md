# ShoeFinder

The prototype includes the Dockerized foundation, catalogue domain layer, English-only Filament workflow, public read-only API, localized comparison pages, tracked retailer redirects, SEO output, product-feed import tooling, and focused verification.

## Requirements

- Docker Desktop with Docker Compose

No host installation of PHP, Composer, Node.js, npm, or PostgreSQL is required.

## Development setup

```sh
cp .env.example .env
cp backend/.env.example backend/.env
docker compose build
docker compose run --rm backend-php php artisan key:generate
docker compose run --rm backend-php php artisan storage:link
docker compose up -d
docker compose run --rm backend-php php artisan migrate --seed
```

Open:

- Application: <http://localhost:8080>
- English application: <http://localhost:8080/en/>
- Catalogue: <http://localhost:8080/catalogue>
- English catalogue: <http://localhost:8080/en/catalogue>
- Contact: <http://localhost:8080/contact>
- Privacy policy: <http://localhost:8080/privacy>
- Affiliate disclosure: <http://localhost:8080/affiliate-disclosure>
- Sitemap: <http://localhost:8080/sitemap.xml>
- Robots rules: <http://localhost:8080/robots.txt>
- Laravel health: <http://localhost:8080/up>
- Filament admin: <http://localhost:8080/admin>
- Adminer database viewer: <http://localhost:8081>
- Catalogue API: <http://localhost:8080/api/v1/shoes>
- Catalogue filters: <http://localhost:8080/api/v1/catalog-filters>

Adminer is available only in the development stack. Use these values to sign in:

- System: `PostgreSQL`
- Server: `postgres`
- Username: the `POSTGRES_USER` value from `.env`
- Password: the `POSTGRES_PASSWORD` value from `.env`
- Database: the `POSTGRES_DB` value from `.env`

Create the first administrator interactively:

```sh
docker compose run --rm backend-php php artisan make:filament-user
```

Two-factor authentication is required for every administrator. On the first sign-in, scan the QR code with a TOTP-compatible authenticator app, verify the temporary code, and store the generated recovery codes outside the application. Two-factor settings and recovery-code regeneration are available from the administrator profile. The profile email is read-only because production panel access uses the configured `FILAMENT_ADMIN_EMAIL` allowlist.

If an administrator loses both the authenticator and every recovery code, reset enrollment from a trusted shell. Replace the example email before running the command. The next password sign-in will require fresh enrollment:

```sh
docker compose exec -T backend-php php artisan tinker --execute="\$user = App\\Models\\User::where('email', 'admin@example.com')->firstOrFail(); \$user->saveAppAuthenticationSecret(null); \$user->saveAppAuthenticationRecoveryCodes(null);"
```

Useful commands:

```sh
docker compose ps
docker compose logs -f
docker compose logs -f backend-worker
docker compose logs -f backend-scraper-worker
docker compose exec -T backend-php php artisan test
docker compose exec -T frontend npm run test
./docker/test-postgres.sh
docker compose down
docker compose up -d
```

The default test suite uses in-memory SQLite. `./docker/test-postgres.sh` creates a temporary PostgreSQL database, runs the database integration tests, and removes that database.

`docker compose down` keeps the database, dependency, and media volumes. Do not use `docker compose down -v` unless intentionally deleting all local project data.

`OFFER_STALE_AFTER_HOURS` controls how long a checked retailer listing can define a lowest price. It defaults to 168 hours.

`CONTACT_EMAIL` sets the public address shown on the localized contact page. When it is empty, the page shows a pre-launch fallback instead of a non-working email link. Set it before public launch.

The public API supports:

- `GET /api/v1/shoes`
- `GET /api/v1/shoes/{slug}`
- `GET /api/v1/catalog-filters`
- `GET /go/{listing}`

Latvian is the default API language. Pass `locale=en` for English localized fields. The complete request and response rules are in [notes/api-contract.md](notes/api-contract.md).

## Product-feed imports

The command-line feed importer runs synchronously. Administrator uploads and Apply actions run on the Redis-backed `imports` queue. Create the matching retailer in Filament before importing. Its slug must match one configured feed:

- `sole-market`
- `urban-step`
- `sneaker-point`
- `apavu-nams`

Preview a fixture without changing the database:

```sh
docker compose exec -T backend-php php artisan feeds:import sole-market clean/sole-market.csv
```

Apply accepted records explicitly:

```sh
docker compose exec -T backend-php php artisan feeds:import sole-market clean/sole-market.csv --apply
```

The path may be absolute, relative to `backend/`, or relative to `backend/tests/Fixtures/ProductFeeds/`.

The command updates listings matched by retailer external ID or SKU. It can create a listing only when GTIN, manufacturer variant code, or manufacturer style code plus colour identifies one existing shoe variant. It does not create shoes or reference data. Weak, conflicting, and unmatched records are reported for manual review.

Invalid input prevents the entire apply operation. A listing missing from one snapshot is reported but remains unchanged.

Administrators can also open **Imports** in Filament:

1. Select a configured retailer and upload a feed file.
2. Wait for the background preview to reach **Ready for review**. The list refreshes automatically.
3. Select **View changes** on any row to compare current and incoming listing fields and sizes.
4. Link safe unmatched records to an existing variant, create catalogue data after review, confirm a matched-listing identity update, or ignore the row.
5. Select **Import** after every review item has a decision.
6. Wait for the queued import to reach **Imported**.

Uploaded files use private Laravel storage. Development shares them through the backend bind mount. Production shares them with workers through a named volume. The UI accepts files up to 10 MB and previews up to 5,000 records. When exactly one listing was matched, the review modal compares stored and incoming identities and can confirm an update. If external ID and SKU point to two different listings, correct the catalogue or source data and upload a new file.

Administrators can start a Ballzy product-page check from the **Product-page scraper** dashboard table or the **Scrape runs** section. The dedicated scraper worker processes manual Ballzy listing URLs sequentially on the `scrapes` queue. A completed run shows price, availability, and size differences without changing the catalogue. Select **Apply preview** to apply every successful result together; failed pages remain unchanged. The workflow includes inactive Ballzy listings so returned stock can reactivate an offer.

Set `SCRAPER_USER_AGENT` to an identifiable value containing the production URL or contact address. `SCRAPER_CRAWL_DELAY_MS` defaults to 2,000 milliseconds to respect Ballzy's crawl delay. The scraper accepts only allowlisted HTTPS Ballzy product URLs and does not store downloaded page HTML.

Queue operations:

```sh
docker compose logs -f backend-worker
docker compose logs -f backend-scraper-worker
docker compose exec -T backend-php php artisan queue:monitor redis:imports --max=1000
docker compose exec -T backend-php php artisan queue:monitor redis:scrapes --max=1000
docker compose exec -T backend-php php artisan queue:failed
docker compose exec -T backend-php php artisan queue:retry all
docker compose exec -T backend-php php artisan queue:restart
```

`queue:retry all` retries every failed job. Use a failed job ID instead when other queues are added. A failed feed job also marks its import as **Failed** in Filament.

Creating a colour variant requires selecting an existing variant from the correct shoe model. Select an existing shared colourway when it already exists, or leave that field empty and enter a new stable code, canonical name, and filter colours. Colourway names are shown unchanged in Latvian and English. Filter colours are localized and affect only catalogue filtering. The selected shoe cannot use the same colourway twice. The colourway, variant, and retailer offer are connected together only when the full import is applied.

Creating a new shoe model requires selecting an existing brand and category, then confirming the official shoe name, stable slug, audience, manufacturer codes, and colour details. Saving the review creates nothing. Applying the import creates the shoe, first colour variant, retailer offer, and sizes together. Add curated images later in the Shoe resource.

Use **Colourways** in Filament to review each shared colourway and assign every visible filter colour. For example, `White/Black` should have both `White` and `Black`.

## Code quality

Check PHP formatting:

```sh
docker compose exec -T backend-php composer format:check
```

Check frontend linting and formatting:

```sh
docker compose exec -T frontend npm run test
docker compose exec -T frontend npm run quality
```

Apply automatic fixes:

```sh
docker compose exec -T backend-php composer format
docker compose exec -T frontend npm run lint:fix
docker compose exec -T frontend npm run lint:css:fix
docker compose exec -T frontend npm run format
```

## GitLab CI/CD

The GitLab pipeline runs the Laravel and Nuxt tests, Pint, ESLint, Stylelint, and Prettier on every branch and merge request. Commits on the default branch and tags also build and push commit-addressed PHP, backend-web, frontend, and gateway images to the GitLab Container Registry.

The image jobs use Docker-in-Docker, so their GitLab runner must allow privileged Docker services.

Production deployment is a protected manual action on the default branch. It is omitted from the pipeline until all of these protected GitLab CI/CD variables exist:

- `PRODUCTION_HOST`: VPS hostname or IP address.
- `PRODUCTION_SSH_USER`: unprivileged SSH deployment user with Docker access.
- `PRODUCTION_SSH_PRIVATE_KEY`: private deployment key, preferably a GitLab file-type variable.
- `PRODUCTION_KNOWN_HOSTS`: the VPS host key captured and verified outside CI, preferably a file-type variable.
- `PRODUCTION_DEPLOY_PATH`: absolute remote directory containing the production deployment.

The VPS must already contain `$PRODUCTION_DEPLOY_PATH/.env.production` with the application secrets documented below. CI uploads only the production Compose file, release helper, and non-secret immutable image references. It never replaces `.env.production`.

After the manual deployment starts the new containers, a separate `migrate:production` job runs migrations and reference-data seeders. A final `health:production` job waits for every container health check and verifies that Laravel can reach PostgreSQL and Redis through `/up`. Set the optional `PRODUCTION_HEALTHCHECK_URL` to the public `/up` URL to add an outside-the-VPS HTTP check.

Production containers never migrate during startup. Do not combine `deploy:production` and `migrate:production`; keeping the schema change visible as its own pipeline job makes failures and recovery decisions explicit.

Pint formats PHP. ESLint checks Vue, JavaScript, and TypeScript. Stylelint checks and orders public CSS. Prettier formats frontend source and sorts Tailwind classes.

## Production build and migrations

Create a protected production environment file outside version control. Set a strong `POSTGRES_PASSWORD`, a persistent generated `APP_KEY`, the final HTTPS `APP_URL`, the public `CONTACT_EMAIL`, and the exact `FILAMENT_ADMIN_EMAIL`. Production Compose rejects missing required values. `CONTACT_EMAIL` remains optional at the Compose level so isolated builds can run, but it is required before public launch.

Keep the same `APP_KEY` between deployments. Changing it invalidates encrypted application data and active sessions.

Before each deployment:

1. Create and verify an application backup.
2. Validate the resolved Compose configuration.
3. Build the new images.
4. Start PostgreSQL and Redis.
5. Run migrations as an explicit deployment step.
6. Seed reference data.
7. Start or replace the application services.
8. Check service health and the public `/up` response.

```sh
docker compose --env-file .env.production -f compose.production.yaml config --quiet
./docker/backup.sh --tier daily
docker compose --env-file .env.production -f compose.production.yaml build
docker compose --env-file .env.production -f compose.production.yaml up -d postgres redis
docker compose --env-file .env.production -f compose.production.yaml run --rm backend-php php artisan migrate --force --no-interaction
docker compose --env-file .env.production -f compose.production.yaml run --rm backend-php php artisan db:seed --force --no-interaction
docker compose --env-file .env.production -f compose.production.yaml up -d
docker compose --env-file .env.production -f compose.production.yaml ps
docker compose --env-file .env.production -f compose.production.yaml exec -T proxy wget -q -O - http://127.0.0.1/up
```

Production containers never run migrations during startup. The migration command is a separate deployment step.

`docker/backup.sh` creates a PostgreSQL custom-format dump, a public-media archive, metadata, and SHA-256 checksums. Daily, weekly, and monthly tiers have independent local retention. Optional `rclone` upload sends completed sets to private remote storage. Verify any set without touching the live database:

```sh
./docker/verify-backup.sh /var/backups/shoe-finder/daily/shoe-finder-YYYYMMDDTHHMMSSZ
```

The systemd templates, remote-storage settings, secret handling, and recovery procedure are documented in [docker/systemd/README.md](docker/systemd/README.md). Store `.env.production` and the persistent `APP_KEY` separately in an encrypted secrets store. They are intentionally excluded from application backups.

Create the first administrator only after migrations succeed:

```sh
docker compose --env-file .env.production -f compose.production.yaml run --rm backend-php php artisan make:filament-user
```

The administrator must complete mandatory authenticator-app enrollment on the first production sign-in and store the recovery codes securely.

The public Compose proxy listens for plain HTTP. Terminate HTTPS at the hosting platform or an external load balancer. Set HSTS there, where the TLS connection is known. Forward the original host, port, and protocol headers to the application.

The production frontend runs as an unprivileged user. PHP hides runtime errors and uses production OPcache settings. The public proxy sends baseline content-type, frame, referrer, and browser-permission headers. Container logs rotate at three 10 MB files per service.

Run the isolated production proof after deployment changes:

```sh
./docker/verify-production.sh
```

It builds production images, starts fresh isolated volumes, confirms startup did not create the migration table, runs migrations explicitly, checks reference data and public routes, restarts the stack, and removes its temporary project.

Do not run `migrate:rollback` automatically during a failed deployment. Stop the rollout and restore the previous application image only when it is compatible with the migrated schema. Restore the database backup when a migration is not backward-compatible.

Uploaded media and private feed files use named Docker volumes for the prototype. A production deployment should later move both to S3-compatible object storage through Laravel’s filesystem abstraction.

## Project memory

Read [notes/current-state.md](notes/current-state.md) before starting work. The complete notes workflow and staged execution plan are indexed in [notes/README.md](notes/README.md).
