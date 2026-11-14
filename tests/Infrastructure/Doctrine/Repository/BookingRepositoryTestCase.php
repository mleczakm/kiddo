<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class BookingRepositoryTestCase extends KernelTestCase
{
    protected BookingRepository $bookingRepository;

    protected EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        static::assertInstanceOf(BookingRepository::class, $bookingRepository);
        $this->bookingRepository = $bookingRepository;
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
