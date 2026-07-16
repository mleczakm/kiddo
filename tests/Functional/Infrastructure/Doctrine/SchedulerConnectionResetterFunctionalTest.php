<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Doctrine\SchedulerConnectionResetter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Scheduler\Event\PreRunEvent;

#[Group('functional')]
final class SchedulerConnectionResetterFunctionalTest extends KernelTestCase
{
    private Connection $connection;

    private Connection $cacheConnection;

    private SchedulerConnectionResetter $resetter;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->cacheConnection = $container->get('doctrine.dbal.cache_connection');
        $this->resetter = $container->get(SchedulerConnectionResetter::class);
    }

    public function testConnectionEnsurerIsCompositeIncludingCache(): void
    {
        $ensurer = self::getContainer()->get(ConnectionEnsurerInterface::class);

        $this->connection->close();
        $this->cacheConnection->close();
        $this->assertFalse($this->connection->isConnected());
        $this->assertFalse($this->cacheConnection->isConnected());

        $ensurer->ensureConnection();

        $this->assertTrue($this->connection->isConnected());
        $this->assertTrue($this->cacheConnection->isConnected());
        $this->assertEquals(1, $this->connection->executeQuery('SELECT 1')->fetchOne());
        $this->assertEquals(1, $this->cacheConnection->executeQuery('SELECT 1')->fetchOne());
    }

    public function testOnPreRunEnsuresConnectionIsActive(): void
    {
        // Ensure connection is initially connected
        $this->connection->connect();
        $this->assertTrue($this->connection->isConnected());

        // Create a mock PreRunEvent
        $event = $this->createMock(PreRunEvent::class);

        // Call the resetter
        $this->resetter->onPreRun($event);

        // Verify connection is still connected and can execute queries
        $this->assertTrue($this->connection->isConnected());
        $result = $this->connection->executeQuery('SELECT 1');
        $this->assertEquals(1, $result->fetchOne());
    }

    public function testOnPreRunReconnectsDisconnectedConnection(): void
    {
        // Close both connections to simulate a lost database
        $this->connection->close();
        $this->cacheConnection->close();
        $this->assertFalse($this->connection->isConnected());
        $this->assertFalse($this->cacheConnection->isConnected());

        // Create a mock PreRunEvent
        $event = $this->createMock(PreRunEvent::class);

        // Call the resetter
        $this->resetter->onPreRun($event);

        // Verify both connections are reconnected and can execute queries
        $this->assertTrue($this->connection->isConnected());
        $this->assertTrue($this->cacheConnection->isConnected());
        $this->assertEquals(1, $this->connection->executeQuery('SELECT 1')->fetchOne());
        $this->assertEquals(1, $this->cacheConnection->executeQuery('SELECT 1')->fetchOne());
    }

    public function testOnPreRunHandlesMultipleSequentialCalls(): void
    {
        // Create a mock PreRunEvent
        $event = $this->createMock(PreRunEvent::class);

        // Call the resetter multiple times
        for ($i = 0; $i < 5; $i++) {
            $this->resetter->onPreRun($event);
            $this->assertTrue($this->connection->isConnected());
            $result = $this->connection->executeQuery('SELECT 1');
            $this->assertEquals(1, $result->fetchOne());
        }
    }
}
