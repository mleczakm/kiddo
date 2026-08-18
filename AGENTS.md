# Agent Guide — Kiddo

Symfony PHP app. Run all PHP/Composer commands from the project root via Docker Compose.

**Prefix:** `docker compose run --rm php <command>`

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

@RTK.md
