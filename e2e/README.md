# End-to-end tests

[Playwright](https://playwright.dev) tests that drive the real application in a browser:
login through the e-mailed magic link, the public catalogue, the admin lesson lifecycle,
reservations. They run **entirely in containers** (`compose.e2e.yaml`) - nothing to install on the
host, no port to free, and your dev database is never touched (the stack has its own in-memory
PostgreSQL, its own Mailpit and a freshly seeded demo dataset).

## Run

```bash
docker compose -f compose.e2e.yaml up -d --wait php                       # build + start app, db, mailpit, seed
E2E_UID=$(id -u) E2E_GID=$(id -g) docker compose -f compose.e2e.yaml run --rm e2e
docker compose -f compose.e2e.yaml down -v                                # throw everything away
```

`up --wait` returns once the app answers on `/login` (migrated and seeded). The suite is safe to
re-run against the same stack; for a pristine dataset run `down -v` first.

Reports land in `e2e/playwright-report/` (HTML) and `e2e/test-results/` (traces, screenshots, videos
of failures). Open a trace with `npx playwright show-trace <trace.zip>`.

Run one spec: `... run --rm e2e sh -c "npx playwright test lesson-lifecycle"`.

## CI

`.github/workflows/e2e.yml` does the same on every PR and push to `master` and uploads the report as an
artifact. Add the `E2E` check to the branch protection rules if it should block merges.

## Writing tests

- Log in with `loginAs(page, DEMO.admin | DEMO.parent | DEMO.instructor)` from `support/auth.ts`; it
  reads the magic link from Mailpit like a real user.
- Seeded data comes from `bin/console app:dev:seed-demo`; titles are prefixed `[DEMO]`.
- Prefer `data-testid` (see `templates/admin/lesson_show.html.twig`) over translated text.
- Tests share one database and run serially: restore what you change (see `lesson-lifecycle.spec.ts`).
- The Playwright image tag in `compose.e2e.yaml` must match `@playwright/test` in `package.json`.
