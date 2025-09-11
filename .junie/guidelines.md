# Kiddo – Project Development Guidelines

Audience: Advanced PHP/Symfony developers contributing to Kiddo. This document captures project-specific knowledge: how we build, test, analyze, and common pitfalls. Commands are given for Docker-first workflow (preferred), with local equivalents when useful.

## 1) Build and Configuration

- Runtime/Tooling
  - PHP: >= 8.4
  - Symfony: 7.3.*
  - Doctrine ORM: 3.x; Postgres with JSON features (martin-georgiev/scienta packages)
  - Node is not required; assets use Symfony AssetMapper/Tailwind via Symfony bundle.

- Primary workflow uses Docker Compose services defined in compose.yaml.
  - Use the php service to run composer, Symfony console, tests.
  - Typical shell prefix:
    - docker compose run --rm php <command>
    - docker compose exec -T php <command> (when the stack is up)

- One-time bootstrap
  - Install PHP deps: docker compose run --rm php composer install
  - Build Tailwind CSS: docker compose run --rm php bin/console tailwind:build
  - Start services: docker compose up -d
  - Initialize DB schema (dev): docker compose run --rm php bin/console doctrine:schema:update --force

- Environments
  - .env, .env.local, .env.test are standard Symfony; tests force APP_ENV=test via phpunit.xml.dist.
  - KERNEL_CLASS=App\Kernel is already set in phpunit.xml.dist.

- Useful Symfony commands
  - Cache clear: docker compose run --rm php bin/console cache:clear
  - Messenger transports/workers (if used in features you touch): docker compose run --rm php bin/console messenger:consume -vv
  - Assets re-build: docker compose run --rm php bin/console tailwind:build

## 2) Testing

- Overview
  - PHPUnit 11 is used with phpunit.xml.dist and tests/bootstrap.php
  - Test suites:
    - Smoke: tests/Smoke
    - Unit: tests/Application, tests/Domain, tests/Infrastructure
    - Functional: tests/Functional (DB-backed). Setup is automated via composer scripts.
  - Doctrine tests use DAMA\DoctrineTestBundle for isolation.

- Running tests (Docker)
  - All tests: docker compose run --rm php bin/phpunit
  - Unit only: docker compose run --rm php composer tests
    - This runs the grouped scripts; to run Unit specifically: docker compose run --rm php bin/phpunit --testsuite "Unit"
  - Smoke only: docker compose run --rm php composer tests:smoke
  - Functional only: docker compose run --rm php composer tests:functional
    - This triggers tests:functional:setup which will:
      - Create DB if missing: doctrine:database:create --if-not-exists --env=test
      - Update schema: doctrine:schema:update --force --env=test

- Running tests (local, no Docker)
  - composer install
  - bin/console doctrine:database:create --if-not-exists --env=test
  - bin/console doctrine:schema:update --force --env=test
  - vendor/bin/simple-phpunit or bin/phpunit

- Adding a new test
  - Place unit tests under tests/Domain|Application|Infrastructure depending on layer; smoke tests under tests/Smoke; functional tests under tests/Functional and rely on the test DB and Symfony kernel.
  - Use tests/bootstrap.php for env bootstrapping; APP_ENV is forced to test in phpunit.xml.dist.
  - Example minimal unit test (no DB/kernel):
    - File path suggestion: tests/Domain/Demo/TruthTest.php
    - Contents:
      // --- file: tests/Domain/Demo/TruthTest.php ---
      // <?php
      // declare(strict_types=1);
      // namespace App\\Tests\\Domain\\Demo;
      // use PHPUnit\\Framework\\TestCase;
      // final class TruthTest extends TestCase
      // {
      //     public function test_it_works(): void
      //     {
      //         self::assertTrue(true);
      //     }
      // }
  - Run just this test: docker compose run --rm php bin/phpunit --filter TruthTest

- Verifying existing suites
  - With a running stack, the following have been verified:
    - phpunit XML is valid and points to tests/bootstrap.php
    - Composer scripts are wired: tests, tests:smoke, tests:functional and setup are defined.

## 3) Quality Gates and Developer Tooling

- Static analysis
  - PHPStan 2.x configured via phpstan.dist.neon (+ baseline). Run:
    - docker compose run --rm php vendor/bin/phpstan analyse --memory-limit=-1
  - Baseline refresh flow is encoded in composer sa:fix script.

- Coding standard
  - ECS (Symplify Easy Coding Standard) configured in ecs.php
    - Check: docker compose run --rm php vendor/bin/ecs check
    - Fix: docker compose run --rm php vendor/bin/ecs check --fix

- Rector
  - Rector config in rector.php; use for refactors:
    - docker compose run --rm php vendor/bin/rector process

- QA umbrella scripts
  - composer sa to run: composer validate, audit, twig lint, phpstan, ecs
  - composer qa groups Static analysis and Tests

## 4) Project-specific Notes and Pitfalls

- Workflows
  - Symfony Workflow is used (config/packages/workflow.yaml). When changing booking/payment transitions, update tests around message handlers and ensure state machine services are properly autowired. Failing to tag the correct workflow or transition names will surface at runtime; prefer adding smoke tests for transitions.

- Messenger and Handlers
  - Message handlers exist under src/MessageHandler. Handlers typically inject WorkflowInterface instances scoped to the Booking state machine. Validate constructor signatures and ensure handlers don’t perform DB lookups in constructors (there is a broken example in RescheduleLessonBookingHandler.php in the current tree). Prefer explicit __invoke(YourMessage $command) for side effects.

- Serializer Normalizers
  - Custom normalizers live under src/Infrastructure/Symfony/Serializer. If you extend normalization of DTOs (e.g., LessonMap), add/adjust corresponding tests in tests/Infrastructure/Symfony/Serializer.

- Database for tests
  - Functional tests rely on schema:update in test env. If you introduce migrations affecting test stability, consider resetting schema or using Foundry for factories.

- Translations/UI
  - Twig components under templates/components are used by UI tests in tests/UserInterface/Http/Component. When changing templates, expect brittle selectors in tests; prefer data-testid attributes for stability.

- Money and Time
  - brick/money is used. Keep currency math in domain services; don’t format in domain layer. Use value objects where available.

## 5) Commands Cheat Sheet

- Install & bootstrap (Docker):
  - docker compose run --rm php composer install
  - docker compose run --rm php bin/console tailwind:build
  - docker compose up -d
  - docker compose run --rm php bin/console doctrine:schema:update --force

- Test suites:
  - docker compose run --rm php bin/phpunit
  - docker compose run --rm php bin/phpunit --testsuite "Unit"
  - docker compose run --rm php composer tests:smoke
  - docker compose run --rm php composer tests:functional

- QA:
  - docker compose run --rm php composer sa
  - docker compose run --rm php vendor/bin/phpstan analyse --memory-limit=-1
  - docker compose run --rm php vendor/bin/ecs check --fix

Notes:
- If you add a demo test as in the example above for documentation purposes, remove it from the repository after verification; it should not remain committed.
