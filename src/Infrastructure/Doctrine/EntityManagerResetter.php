<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;

#[AsEventListener(event: 'kernel.terminate', method: 'reset')]
final readonly class EntityManagerResetter implements ServicesResetterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function reset(): void
    {
        $this->entityManager->close();
    }
}
