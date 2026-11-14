<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\PlatformBillingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class PlatformBillingServiceTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected PlatformBillingService $service;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $service = self::getContainer()->get(PlatformBillingService::class);
        static::assertInstanceOf(PlatformBillingService::class, $service);
        $this->service = $service;
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }
}
