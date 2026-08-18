<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Sentry\State\HubInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * sentry/sentry-symfony registers `Sentry\State\HubInterface` bound to the DSN-configured
 * client (see vendor/sentry/sentry-symfony's own services.yaml: HubAdapter::getInstance() +
 * bindClient()). For 5 months, config/services.yaml redefined that same service id at the
 * top level with a bare `SentrySdk::getCurrentHub()` factory (added only to satisfy
 * BGalati\MonologSentryHandler\SentryHandler's autowired $hub argument), which silently
 * replaced the bundle's definition everywhere — including prod, where the resulting hub was
 * never bound to a client. Every event sent through the Monolog handler was a no-op, with no
 * error anywhere, for the entire time.
 *
 * A container-compilation or functional test can't safely cover this: SentryBundle is only
 * registered for 'prod' (config/bundles.php), and today's coroutine changes to the Kernel
 * currently break booting it more than once per process. This is the cheapest test that
 * still directly pins the regression down.
 */
#[Group('unit')]
final class SentryHubServiceWiringTest extends TestCase
{
    public function testTopLevelServicesDoNotOverrideTheSentryHub(): void
    {
        self::assertArrayNotHasKey(
            HubInterface::class,
            $this->block(null),
            'config/services.yaml must not define Sentry\State\HubInterface at the top level: '
            . 'doing so overrides the client-bound Hub that sentry/sentry-symfony registers for prod, '
            . 'silently dropping every event sent through BGalati\MonologSentryHandler\SentryHandler '
            . '(see the comment above the `when@dev`/`when@test` blocks in this same file).',
        );
    }

    public function testDevAndTestEnvironmentsStillProvideAFallbackHub(): void
    {
        foreach (['when@dev', 'when@test'] as $block) {
            self::assertArrayHasKey(
                HubInterface::class,
                $this->block($block),
                "config/services.yaml's \"{$block}\" block must provide a fallback Sentry\State\HubInterface "
                . 'so BGalati\MonologSentryHandler\SentryHandler can still be autowired outside prod '
                . '(SentryBundle is only registered for the prod environment).',
            );
        }
    }

    /**
     * Returns the `services:` map either at the document root (when $envBlock is null) or
     * nested under a `when@<env>:` block.
     *
     * @return array<string, mixed>
     */
    private function block(?string $envBlock): array
    {
        $config = Yaml::parseFile(dirname(__DIR__, 3) . '/config/services.yaml');
        self::assertIsArray($config);

        $scope = $config;
        if ($envBlock !== null) {
            self::assertArrayHasKey($envBlock, $config);
            self::assertIsArray($config[$envBlock]);
            $scope = $config[$envBlock];
        }

        self::assertArrayHasKey('services', $scope);
        self::assertIsArray($scope['services']);

        return $scope['services'];
    }
}
