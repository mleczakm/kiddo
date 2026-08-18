<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class DoctrineConnectionEnsurer implements ConnectionEnsurerInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function ensureConnection(): void
    {
        $this->discardActiveTransaction();

        $maxRetries = 3;
        $retryDelayMs = 1000;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $this->connection->executeQuery('SELECT 1');

                return;
            } catch (Exception $e) {
                if ($i < ($maxRetries - 1)) {
                    if ($this->connection->isConnected()) {
                        $this->connection->close();
                    }

                    usleep($retryDelayMs * 1000);
                } else {
                    throw $e;
                }
            }
        }
    }

    private function discardActiveTransaction(): void
    {
        if (!$this->connection->isTransactionActive()) {
            return;
        }

        try {
            $this->connection->rollBack();
        } catch (\Throwable) {
            if ($this->connection->isConnected()) {
                $this->connection->close();
            }
        }
    }
}
