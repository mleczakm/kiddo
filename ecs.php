<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([__DIR__ . '/config', __DIR__ . '/public', __DIR__ . '/src', __DIR__ . '/tests'])
    ->withRootFiles()
    // add a single rule
    ->withRules([NoUnusedImportsFixer::class])
    ->withPhpCsFixerSets(
        doctrineAnnotation: true,
        per: true,
        perRisky: true,
        php84Migration: true,
        phpunit100MigrationRisky: true
    )
    // add sets - group of rules
    ->withPreparedSets(psr12: true, common: true, symplify: true, strict: true, cleanCode: true)
;
