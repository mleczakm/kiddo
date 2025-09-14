# Copilot Coding Agent Onboarding Instructions

## 1. Repository Overview

- **Purpose:**
  - System for managing school class payments, parent registration, and treasurer operations. Supports user registration, payment tracking, and administrative panels for class groups.
- **Type:**
  - Web application backend (Symfony PHP framework).
- **Languages/Frameworks:**
  - PHP (>=8.1), Symfony, Doctrine ORM, Twig (templates), Brick/Money (money handling).
- **Size:**
  - Medium-sized, multi-module structure (src/, templates/, tests/, config/, migrations/).

## 2. Build, Test, and Validation Instructions

### Environment Setup
- **Docker is required.** All PHP commands must be run in the PHP container.
- **Always use:**
  - `docker compose run --rm php <command>`
- **Composer dependencies:**
  - Install with: `docker compose run --rm php composer install`
- **Database:**
  - Migrations: `docker compose run --rm php bin/console doctrine:schema:update --force`

### Build/Bootstrap
- No explicit build step; ensure dependencies are installed and migrations are run.
- If cache issues occur, clear with: `docker compose run --rm php bin/console cache:clear`

### Testing
- **Unit/Functional tests:**
  - Run: `docker compose run --rm php vendor/bin/phpunit`
  - Config: `phpunit.xml.dist`
- **Test bootstrap:**
  - `tests/bootstrap.php` is loaded automatically.
- **Test coverage:**
  - Output in `target/coverage/` if configured.

### Linting/Static Analysis
- **ECS (Easy Coding Standard):**
  - Run: `docker compose run --rm php vendor/bin/ecs`
  - Config: `ecs.php`
- **PHPStan:**
  - Run: `docker compose run --rm php vendor/bin/phpstan analyse`
  - Config: `phpstan.dist.neon`, baseline: `phpstan-baseline.neon`

### Other Validation
- **Symfony config validation:**
  - Run: `docker compose run --rm php bin/console lint:yaml config/`
- **Twig templates:**
  - Located in `templates/`, referenced by controllers in `src/UserInterface/`.

### Common Issues & Workarounds
- **Always run composer install before any other PHP command.**
- **If migrations fail, check database container status.**
- **If tests fail due to missing classes, clear cache and re-run composer install.**
- **If ECS or PHPStan fail, check config files for custom rules.**

## 3. Project Layout & Architecture

- **src/**: Main application code
  - `Application/`, `Domain/`, `Entity/`, `Service/`, `UserInterface/`, etc.
- **templates/**: Twig templates for views
- **config/**: Symfony configuration
  - `services.yaml`, `routes.yaml`, `packages/`, `routes/`
- **migrations/**: Doctrine migration scripts
- **tests/**: Unit and functional tests
  - `Functional/` for HTTP/controller tests
- **public/**: Entry point (`index.php`), public assets
- **bin/**: Symfony console, PHPUnit
- **var/**: Cache and logs

### Key Files in Repo Root
- `composer.json`, `composer.lock`: PHP dependencies
- `phpunit.xml.dist`: PHPUnit config
- `ecs.php`: ECS config
- `phpstan.dist.neon`, `phpstan-baseline.neon`: PHPStan config
- `README.md`: Project documentation
- `symfony.lock`: Symfony package lock
- `compose.yaml`, `compose.override.yaml`: Docker Compose config

### Directory Structure (next level)
- **src/Application/**: Application services, commands
- **src/Domain/**: Domain models, business logic
- **src/Entity/**: Doctrine entities
- **src/Service/**: Service classes
- **src/UserInterface/**: Controllers, forms
- **tests/Functional/**: HTTP/controller tests (see `ClassPayBuddyControllerTest.php`)
- **config/packages/**: Symfony package configs
- **config/routes/**: Route definitions

## 4. CI/CD & Validation
- **No explicit GitHub Actions or CI config found.**
- **Manual validation:**
  - Run ECS, PHPStan, PHPUnit before check-in.
  - Validate migrations and config.

## 5. Additional Notes
- **Trust these instructions.** Only search for additional info if these are incomplete or in error.
- **Always use Docker for PHP commands.**
- **Do not run PHP, Composer, or Symfony commands directly on host.**
- **All validation steps above should be run before submitting changes.**

---

For further details, consult `README.md` and config files in the repo root and `config/`.

