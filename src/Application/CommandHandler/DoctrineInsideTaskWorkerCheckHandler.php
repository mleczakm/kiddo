<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\DoctrineInsideTaskWorkerCheck;
use Psr\SimpleCache\CacheInterface;
use SymfonyHealthCheckBundle\Check\DoctrineORMCheck;

final readonly class DoctrineInsideTaskWorkerCheckHandler
{
    public function __construct(
        private CacheInterface $cache,
        private DoctrineORMCheck $check,
    ) {}

    public function __invoke(DoctrineInsideTaskWorkerCheck $command): void
    {
        try {
            $response = $this->check->check();

            $this->cache->set($command->cacheKey, $response->getResult());
        } catch (\Throwable) {
            try {
                $this->cache->set($command->cacheKey, false, 5);
            } catch (\Throwable) {
                // If cache is also down (e.g. DB backed cache and aborted transaction), we can't do much.
            }
        }
    }
}
