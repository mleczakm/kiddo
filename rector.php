<?php

declare(strict_types=1);

use App\Infrastructure\Rector\AddPhpUnitGroupAttributeRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/config', __DIR__ . '/public', __DIR__ . '/src', __DIR__ . '/tests'])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withRootFiles()
    // uncomment to reach your current PHP version
    ->withPhpSets()
    ->withRules([AddPhpUnitGroupAttributeRector::class])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
