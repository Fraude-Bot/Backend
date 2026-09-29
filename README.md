# Fraudebot backend

Laravel 12 / PHP 8.3 JSON API for fraud-intelligence search and entity management.

## Local development

The development stack is defined in the sibling `../docker` directory.

```bash
cd ../docker
docker compose up -d
docker compose exec -T -w /var/www/backend backend php artisan migrate
```

Generate a local admin token only when explicitly needed:

```dotenv
APP_ENV=local
DEV_TOKEN_ENABLED=true
```

Then call `POST /api/admin/token`. This route is not registered outside the local environment. Production users authenticate through `/api/auth/login`; administrative access requires an `admin` or `moderator` role and an `admin:write` token ability.

## Quality checks

```bash
cd ../docker
docker compose exec -T -w /var/www/backend backend composer format:check
docker compose exec -T -w /var/www/backend backend composer analyse
docker compose exec -T -w /var/www/backend backend composer check:architecture
docker compose exec -T -w /var/www/backend backend composer test
docker compose exec -T -w /var/www/backend backend composer audit
```

Tests use in-memory SQLite. The Code checks workflow runs PHPStan (`composer analyse`) and PHPArkitect (`composer check:architecture`) on every push and pull request.

## API overview

- `GET /api/public/reports` — search active fraud records.
- `GET /api/public/scammers/{id}` and `/organizations/{id}` — public profiles.
- `POST /api/auth/register|login|logout` — scoped token lifecycle.
- `/api/admin/*` — scoped scammer and organization administration.
- `GET /api/public/healthcheck` — liveness.
- `GET /api/public/readiness` — database/cache readiness.

The full contract is in [`openapi.yaml`](openapi.yaml). With the Docker stack running, interactive docs are at [http://localhost:9000/scalar](http://localhost:9000/scalar). PHP-FPM serves that page as `www-data`, so `storage/` and `bootstrap/cache` must be writable by that user.

## Architecture

- `app/Domain` contains enums, entities, and value objects.
- `app/Application` orchestrates transactional use cases.
- `app/Repositories` owns public read queries and versioned caching.
- `app/Http` maps requests, authorization, and API resources.
- `app/Models` contains persistence relationships and cache-invalidation hooks.

Public reads expose active entities and reports only. Search and administrative operations are rate-limited and privacy-preserving audit records store hashes rather than raw clues or IP addresses.

## Environment

Start from `.env.example`. Production deployments must set:

- `APP_ENV=production`, `APP_DEBUG=false`, and a generated `APP_KEY`.
- MySQL/Redis connection values.
- `SANCTUM_TOKEN_EXPIRATION` to the desired finite token lifetime.
- `DEV_TOKEN_ENABLED=false`.
- Trusted application/CDN URLs.

Never commit runtime databases, `.env` files, access tokens, or unmasked production exports.
