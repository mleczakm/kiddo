<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Coroutines are switched off for the test environment (see config/packages/swoole.yaml), so the
 * per-coroutine pooling of these services cannot be exercised here. This pins the production
 * configuration instead: dropping one of them silently brings back a cross-request race.
 */
#[Group('unit')]
final class SwooleStatefulServicesConfigTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function mutableSharedState(): iterable
    {
        yield 'component stack (WARSZTATOWNIA-CY)' => ['ux.twig_component.component_stack'];
        yield 'security token storage' => ['security.token_storage'];
        yield 'form registry' => ['form.registry'];
    }

    #[DataProvider('mutableSharedState')]
    public function testServiceHoldingPerRequestStateIsPooledPerCoroutine(string $serviceId): void
    {
        /** @var array{swoole: array{platform: array{coroutines: array{stateful_services: list<string>}}}} $config */
        $config = Yaml::parseFile(__DIR__ . '/../../../config/packages/swoole.yaml');

        static::assertContains($serviceId, $config['swoole']['platform']['coroutines']['stateful_services']);
    }
}
