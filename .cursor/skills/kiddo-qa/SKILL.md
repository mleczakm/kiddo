---
name: kiddo-qa
description: >-
  Run Kiddo (mleczakm/kiddo) tests, QA, and Docker database access. Use when
  working in this repo for composer qa, qa:fix, phpunit, or Postgres queries.
  For the full workflow shared across mleczakm projects, see personal skill
  mleczakm-symfony-qa.
---

# Kiddo QA

This repo follows the shared **mleczakm Symfony Docker QA workflow**. Read and apply the personal skill:

**`~/.cursor/skills/mleczakm-symfony-qa/SKILL.md`**

## Kiddo-specific notes

- Composer package: `mleczakm/kiddo`
- PostgreSQL schema: **`public`** (default)
- Functional test setup: `doctrine:database:create` + `doctrine:migrations:migrate` (no schema drop)
- CI (`.github/workflows/build-and-qa.yml`) uses `app_test` with Postgres 15; local Docker uses Postgres 16

Quick reference — always prefix with `docker compose run --rm php`:

```bash
docker compose up -d db
docker compose run --rm php composer qa:fix   # auto-fix
docker compose run --rm php composer qa       # full gate
docker compose run --rm php composer tests  # all test groups
```
