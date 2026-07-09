<?php

declare(strict_types=1);

namespace App\Infrastructure\Healthcheck;

use Doctrine\DBAL\Connection;
use SymfonyHealthCheckBundle\Check\CheckInterface;
use SymfonyHealthCheckBundle\Dto\Response;

class DoctrineDbalCacheHealthcheck implements CheckInterface
{
    public function __construct(
        private readonly Connection $cacheConnection,
    ) {}

    public function check(): Response
    {
        try {
            $this->cacheConnection->executeQuery(
                $this->cacheConnection->getDatabasePlatform()
                    ->getDummySelectSQL()
            )->free();
        } catch (\Throwable $e) {
            return new Response(
                'doctrine_dbal_cache',
                false,
                'Doctrine DBAL cache connection failed: ' . $e->getMessage()
            );
        }

        return new Response('doctrine_dbal_cache', true, 'Doctrine DBAL cache connection is healthy');
    }
}
