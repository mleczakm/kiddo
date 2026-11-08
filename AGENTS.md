# Agent Guide — Kiddo

Symfony PHP app. Run all PHP/Composer commands from the project root via Docker Compose.

**Prefix:** `docker compose run --rm php <command>` (the `php` service is also the Swoole web container on :9501; the host has no PHP/Swoole, so never run `php`/`composer` outside it).

**After pulling** (when `composer.lock`, `.docker/php/Dockerfile` or the PHP/Swoole pin changed) rebuild the image first, otherwise Composer fails on `ext-swoole`/PHP version and the Kernel fatals on a stale `vendor/`:

```bash
docker compose build php && docker compose up -d --force-recreate db php
docker compose run --rm php composer install
```

Port 9501 must be free (stop any other project's container holding it, e.g. `classy-cash-php-1`). A fresh DB volume needs `docker compose stop php`, then `docker compose run --rm php bin/console doctrine:migrations:migrate`, then `docker compose up -d php` (the running server auto-creates `cache_items` and breaks the migration).

Start the database before DB-backed work (functional/smoke tests, Doctrine):

```bash
docker compose up -d db
```

## Tests

```bash
docker compose run --rm php composer tests
```

Runs unit → smoke → functional (with test DB create + schema update).

| Goal | Command |
|------|---------|
| All test groups | `docker compose run --rm php composer tests` |
| Unit only | `docker compose run --rm php bin/phpunit --group unit` |
| Smoke | `docker compose run --rm php composer tests:smoke` |
| Functional | `docker compose run --rm php composer tests:functional` |
| Filter | `docker compose run --rm php bin/phpunit --filter ClassNameOrMethod` |

## Code quality

| Goal | Command |
|------|---------|
| Auto-fix (Mago lint + format) | `docker compose run --rm php composer qa:fix` |
| Full gate (static analysis + tests) | `docker compose run --rm php composer qa` |

- **`composer qa:fix`** writes changes — review the diff afterward.
- **`composer qa`** = `composer sa` (validate, audit, Twig lint, Mago lint/analyze/format check) + `composer tests`.

### After code changes

1. `docker compose run --rm php composer qa:fix`
2. Review auto-applied changes
3. `docker compose run --rm php composer qa`
4. Fix any remaining issues, then re-run `composer qa`

## State machines

Every status lifecycle is a Symfony Workflow in `config/packages/workflow.yaml` (diagrams + usage rules in
[docs/STATE_MACHINES.md](docs/STATE_MACHINES.md)). Drive transitions through `StateMachinesInterface`
(`can` / `apply` / `applyIfPossible`); do not compare `->status` to string literals or call `setStatus()` hooks directly.

## End-to-end tests

Playwright browser tests live in `e2e/` and run fully containerised (own DB, Mailpit, seeded demo data) via
`compose.e2e.yaml`; CI runs them in `.github/workflows/e2e.yml`. Commands and conventions: [e2e/README.md](e2e/README.md).

