# ShoeFinder

The prototype includes the Dockerized foundation, catalogue domain layer, Latvian Filament workflow, public read-only API, localized comparison pages, tracked retailer redirects, SEO output, product-feed import tooling, and focused verification.

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
- Catalogue API: <http://localhost:8080/api/v1/shoes>
- Catalogue filters: <http://localhost:8080/api/v1/catalog-filters>

Create the first administrator interactively:

```sh
docker compose run --rm backend-php php artisan make:filament-user
```

Useful commands:

```sh
docker compose ps
docker compose logs -f
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

Feed imports run synchronously. Create the matching retailer in Filament before importing. Its slug must match one configured feed:

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

Administrators can also open **Importi** in Filament:

1. Select a configured retailer and upload a feed file.
2. Review the preview. No catalogue data changes during upload.
3. Link safe unmatched records to an existing variant, create a new colour variant, create a new shoe model with its first variant, or ignore them.
4. Select **Importēt** after every review item has a decision.

Uploaded files use private Laravel storage. The UI accepts files up to 10 MB and previews up to 5,000 records. Identity conflicts can only be ignored in the first review workflow. Resolve the catalogue or source data and upload a corrected feed instead of overriding conflicting identifiers.

Creating a colour variant requires selecting an existing variant from the correct shoe model. Select an existing shared colour when it already exists, or leave that field empty and enter a new stable colour code and canonical name. Colour names are shown unchanged in Latvian and English. The selected shoe cannot use the same colour twice. The colour, variant, and retailer offer are connected together only when the full import is applied.

Creating a new shoe model requires selecting an existing brand and category, then confirming the official shoe name, stable slug, audience, manufacturer codes, and colour details. Saving the review creates nothing. Applying the import creates the shoe, first colour variant, retailer offer, and sizes together. Add curated images later in the Shoe resource.

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
4. Start PostgreSQL.
5. Run migrations as an explicit deployment step.
6. Seed reference data.
7. Start or replace the application services.
8. Check service health and the public `/up` response.

```sh
docker compose --env-file .env.production -f compose.production.yaml config --quiet
docker compose --env-file .env.production -f compose.production.yaml build
docker compose --env-file .env.production -f compose.production.yaml up -d postgres
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

Uploaded media uses a named Docker volume for the prototype. A production deployment should later move media to S3-compatible object storage through Laravel’s filesystem abstraction.

## Project memory

Read [notes/current-state.md](notes/current-state.md) before starting work. The complete notes workflow and staged execution plan are indexed in [notes/README.md](notes/README.md).
