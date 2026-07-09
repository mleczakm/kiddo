<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Healthcheck;

use App\Infrastructure\Healthcheck\DoctrineDbalCacheHealthcheck;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class DoctrineDbalCacheHealthcheckTest extends KernelTestCase
{
    private DoctrineDbalCacheHealthcheck $healthcheck;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->healthcheck = self::getContainer()->get(DoctrineDbalCacheHealthcheck::class);
    }

    public function testHealthcheckPassesWhenDatabaseConnectionIsHealthy(): void
    {
        $response = $this->healthcheck->check();

        self::assertTrue($response->getResult());
        self::assertSame('doctrine_dbal_cache', $response->getName());
        self::assertStringContainsString('healthy', $response->getMessage());
    }

    public function testHealthcheckFailsWhenDatabaseConnectionIsUnavailable(): void
    {
        // Create a mock connection that throws an exception
        $mockConnection = $this->createMock(Connection::class);
        $mockConnection
            ->method('getDatabasePlatform')
            ->willThrowException(
                new \RuntimeException('SQLSTATE[HY000]: General error: 7 no connection to the server')
            );

        $healthcheckWithBrokenConnection = new DoctrineDbalCacheHealthcheck($mockConnection);
        $response = $healthcheckWithBrokenConnection->check();

        self::assertFalse($response->getResult());
        self::assertSame('doctrine_dbal_cache', $response->getName());
        self::assertStringContainsString('failed', $response->getMessage());
        self::assertStringContainsString('no connection to the server', $response->getMessage());
    }

    public function testHealthcheckFailsWhenDatabaseThrowsGenericException(): void
    {
        $mockConnection = $this->createMock(Connection::class);
        $mockConnection
            ->method('getDatabasePlatform')
            ->willThrowException(new \Exception('Some database error'));

        $healthcheckWithBrokenConnection = new DoctrineDbalCacheHealthcheck($mockConnection);
        $response = $healthcheckWithBrokenConnection->check();

        self::assertFalse($response->getResult());
        self::assertSame('doctrine_dbal_cache', $response->getName());
        self::assertStringContainsString('failed', $response->getMessage());
        self::assertStringContainsString('Some database error', $response->getMessage());
    }
}
