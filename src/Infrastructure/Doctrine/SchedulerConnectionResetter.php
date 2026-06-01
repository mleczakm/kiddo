<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Scheduler\Event\PreRunEvent;

final readonly class SchedulerConnectionResetter
{
    public function __construct(
        private Connection $connection
    ) {}

    #[AsEventListener(event: PreRunEvent::class)]
    public function onPreRun(PreRunEvent $event): void
    {
        // Ensure fresh connection before each scheduler run
        $this->ensureConnected();
    }

    private function ensureConnected(): void
    {
        $maxRetries = 3;
        $retryDelay = 1000; // milliseconds

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                if (! $this->connection->isConnected()) {
                    $this->connection->connect();
                }

                // Ping the connection to ensure it's still alive
                $this->connection->executeQuery('SELECT 1');
                return;
            } catch (Exception $e) {
                if ($i < $maxRetries - 1) {
                    // Close and retry
                    if ($this->connection->isConnected()) {
                        $this->connection->close();
                    }
                    usleep($retryDelay * 1000);
                } else {
                    throw $e;
                }
            }
        }
    }
}
