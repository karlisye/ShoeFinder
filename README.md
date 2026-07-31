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

Useful commands:

```sh
docker compose ps
docker compose logs -f
docker compose logs -f backend-worker
docker compose exec -T backend-php php artisan test
docker compose exec -T frontend npm run test
./docker/test-postgres.sh
docker compose down
docker compose up -d
```

The default test suite uses in-memory SQLite. `./docker/test-postgres.sh` creates a temporary PostgreSQL database, runs the database integration tests, and removes that database.

`docker compose down` keeps the database, dependency, and media volumes. Do not use `docker compose down -v` unless intentionally deleting all local project data.

`OFFER_STALE_AFTER_HOURS` controls how long a checked retailer listing can define a lowest price. It defaults to 168 hours.

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

Queue operations:

```sh
docker compose logs -f backend-worker
docker compose exec -T backend-php php artisan queue:monitor redis:imports --max=1000
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

Pint formats PHP. ESLint checks Vue, JavaScript, and TypeScript. Stylelint checks and orders public CSS. Prettier formats frontend source and sorts Tailwind classes.

## Production build and migrations

Create a protected production environment file outside version control. Set a strong `POSTGRES_PASSWORD`, a persistent generated `APP_KEY`, the final HTTPS `APP_URL`, and the exact `FILAMENT_ADMIN_EMAIL`. Production Compose rejects missing values.

Keep the same `APP_KEY` between deployments. Changing it invalidates encrypted application data and active sessions.

Before each deployment:

1. Back up PostgreSQL and the media volume through the hosting platform.
2. Validate the resolved Compose configuration.
3. Build the new images.
4. Start PostgreSQL and Redis.
5. Run migrations as an explicit deployment step.
6. Seed reference data.
7. Start or replace the application services.
8. Check service health and the public `/up` response.

```sh
docker compose --env-file .env.production -f compose.production.yaml config --quiet
docker compose --env-file .env.production -f compose.production.yaml build
docker compose --env-file .env.production -f compose.production.yaml up -d postgres redis
docker compose --env-file .env.production -f compose.production.yaml run --rm backend-php php artisan migrate --force --no-interaction
docker compose --env-file .env.production -f compose.production.yaml run --rm backend-php php artisan db:seed --force --no-interaction
docker compose --env-file .env.production -f compose.production.yaml up -d
docker compose --env-file .env.production -f compose.production.yaml ps
docker compose --env-file .env.production -f compose.production.yaml exec -T proxy wget -q -O - http://127.0.0.1/up
```

Production containers never run migrations during startup. The migration command is a separate deployment step.

Create the first administrator only after migrations succeed:

```sh
docker compose --env-file .env.production -f compose.production.yaml run --rm backend-php php artisan make:filament-user
```

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
