# ShoeFinder

Stage 2 provides the Dockerized foundation, catalogue schema, domain models, validation, freshness, pricing, and backend-only price history. Admin resources, APIs, and public catalogue screens are intentionally not implemented yet.

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
- Laravel health: <http://localhost:8080/up>
- Filament admin: <http://localhost:8080/admin>

Create the first administrator interactively:

```sh
docker compose run --rm backend-php php artisan make:filament-user
```

Useful commands:

```sh
docker compose ps
docker compose logs -f
docker compose exec -T backend-php php artisan test
./docker/test-postgres.sh
docker compose down
docker compose up -d
```

The default test suite uses in-memory SQLite. `./docker/test-postgres.sh` creates a temporary PostgreSQL database, runs the database integration tests, and removes that database.

`docker compose down` keeps the database, dependency, and media volumes. Do not use `docker compose down -v` unless intentionally deleting all local project data.

`OFFER_STALE_AFTER_HOURS` controls how long a checked retailer listing can define a lowest price. It defaults to 168 hours.

## Code quality

Check PHP formatting:

```sh
docker compose exec -T backend-php composer format:check
```

Check frontend linting and formatting:

```sh
docker compose exec -T frontend npm run quality
```

Apply automatic fixes:

```sh
docker compose exec -T backend-php composer format
docker compose exec -T frontend npm run lint:fix
docker compose exec -T frontend npm run format
```

Pint formats PHP. ESLint checks Vue, JavaScript, and TypeScript. Prettier formats frontend source and sorts Tailwind classes.

## Production build and migrations

Copy `.env.example` to a secure deployment environment file, replace all development credentials, and provide a generated `APP_KEY`. Set `FILAMENT_ADMIN_EMAIL` to the email of the administrator created through the documented command.

```sh
docker compose -f compose.production.yaml build
docker compose -f compose.production.yaml run --rm backend-php php artisan migrate --force
docker compose -f compose.production.yaml run --rm backend-php php artisan db:seed --force
docker compose -f compose.production.yaml run --rm backend-php php artisan make:filament-user
docker compose -f compose.production.yaml up -d
```

Production containers never run migrations during startup. The migration command is a separate deployment step.

Uploaded media uses a named Docker volume for the prototype. A production deployment should later move media to S3-compatible object storage through Laravel’s filesystem abstraction.

## Project memory

Read [notes/current-state.md](notes/current-state.md) before starting work. The complete notes workflow and staged execution plan are indexed in [notes/README.md](notes/README.md).
