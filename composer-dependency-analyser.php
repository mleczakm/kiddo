<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

// These are declared in "require" and genuinely run in production, but the analyser
// only sees them referenced from a class-string in YAML config or from Symfony's
// runtime bootstrap, so it can't tell they're used outside dev/test paths.
$config->ignoreErrorsOnPackages(
    [
        // Registered as a monolog handler by class name in config/packages/monolog.yaml.
        'bgalati/monolog-sentry-handler',
        // Referenced by class name as a holiday provider in config/services.yaml.
        'mleczakm/aeon-school-holidays',
        // Loaded internally by symfony/runtime's bootstrap (Dotenv::bootEnv()), not
        // via a `use` statement in project code; .env/.env.prod are shipped in every
        // environment, including prod.
        'symfony/dotenv',
        // The concrete symfony/http-client implementation is wired by the
        // `framework.http_client` config; production code only type-hints against
        // HttpClientInterface (from the separate http-client-contracts package).
        'symfony/http-client',
    ],
    [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV],
);

// Symfony bundles registered in config/bundles.php, Composer plugins, Twig
// extensions/filters, and Doctrine custom types/DQL functions referenced by class
// name in config/packages/doctrine.yaml. All are genuinely used, just never
// `use`-imported by name in PHP the analyser scans.
$config->ignoreErrorsOnPackages(
    [
        'carbonphp/carbon-doctrine-types',
        'doctrine/doctrine-migrations-bundle',
        'dragonmantank/cron-expression',
        'dunglas/doctrine-json-odm',
        'martin-georgiev/postgresql-for-doctrine',
        'mleczakm/swoole-bundle-scheduler',
        'mleczakm/twig-now',
        'mleczakm/zip-bomb-honeypot',
        'nyholm/psr7',
        'phpdocumentor/reflection-docblock',
        'scienta/doctrine-json-functions',
        'sentry/sentry-symfony',
        'swoole-bundle/resetter-bundle',
        'swoole-bundle/z-engine',
        'symfony/asset',
        'symfony/asset-mapper',
        'symfony/brevo-mailer',
        'symfony/doctrine-messenger',
        'symfony/expression-language',
        'symfony/flex',
        'symfony/intl',
        'symfony/mailer',
        'symfony/mime',
        'symfony/monolog-bundle',
        'symfony/process',
        'symfony/property-access',
        'symfony/property-info',
        'symfony/runtime',
        'symfony/stimulus-bundle',
        'symfony/translation',
        'symfony/twig-bundle',
        'symfony/ux-dropzone',
        'symfony/ux-turbo',
        'symfony/var-dumper',
        'symfony/var-exporter',
        'symfony/web-link',
        'symfonycasts/tailwind-bundle',
        'tales-from-a-dev/flowbite-bundle',
        'twig/cssinliner-extra',
        'twig/extra-bundle',
        'twig/inky-extra',
        'twig/intl-extra',
    ],
    [ErrorType::UNUSED_DEPENDENCY],
);

// ext-iconv backs iconv-based string handling pulled in transitively by dependencies;
// nothing in the app calls an iconv function by name.
$config->ignoreErrorsOnExtension('ext-iconv', [ErrorType::UNUSED_DEPENDENCY]);

return $config;
