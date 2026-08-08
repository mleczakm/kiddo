<?php

declare(strict_types=1);

use App\Infrastructure\Rector\AddPhpUnitGroupAttributeRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/config', __DIR__ . '/public', __DIR__ . '/src', __DIR__ . '/tests'])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withRootFiles()
    // uncomment to reach your current PHP version
    ->withPhpSets()
    ->withRules([AddPhpUnitGroupAttributeRector::class])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0)
    ->withSkip([
        // swoole-bundle's Proxifier wraps services tagged `swoole_bundle.safe_stateful_service`
        // (see config/services.yaml) to reset per-coroutine state; it can't proxify readonly
        // classes, so making these readonly breaks container compilation under Swoole.
        ReadOnlyClassRector::class => [
            __DIR__ . '/src/Infrastructure/Doctrine/EntityManagerResetter.php',
            __DIR__ . '/src/Infrastructure/ImapEngine/ConnectionCloserOnResetSubscriber.php',
        ],
        ReadOnlyPropertyRector::class => [
            __DIR__ . '/src/Infrastructure/Doctrine/EntityManagerResetter.php',
            __DIR__ . '/src/Infrastructure/ImapEngine/ConnectionCloserOnResetSubscriber.php',
        ],
    ]);
