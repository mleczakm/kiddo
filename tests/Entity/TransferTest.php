<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\Transfer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class TransferTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testSoftDelete(): void
    {
        $transfer = new Transfer(
            '123456789',
            'Test Sender',
            'Test Title',
            '100.00',
            new \DateTimeImmutable('2024-01-01')
        );

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $transferId = $transfer->getId();
        self::assertNotNull($transferId);

        // Verify transfer exists before soft delete
        $foundTransfer = $this->entityManager->find(Transfer::class, $transferId);
        self::assertNotNull($foundTransfer);
        self::assertNull($foundTransfer->getDeletedAt());

        // Soft delete the transfer
        $this->entityManager->remove($transfer);
        $this->entityManager->flush();

        // Clear entity manager to ensure fresh query
        $this->entityManager->clear();

        // Transfer should not be found with regular find (soft delete filter active)
        $deletedTransfer = $this->entityManager->find(Transfer::class, $transferId);
        self::assertNull($deletedTransfer, 'Transfer should not be found when soft delete filter is active');

        // But should be found when soft delete filter is disabled
        $this->entityManager->getFilters()
            ->disable('softdeleteable');
        try {
            $softDeletedTransfer = $this->entityManager->find(Transfer::class, $transferId);
            self::assertNotNull($softDeletedTransfer, 'Transfer should be found when soft delete filter is disabled');
            self::assertNotNull($softDeletedTransfer->getDeletedAt(), 'deletedAt should be set');
        } finally {
            $this->entityManager->getFilters()
                ->enable('softdeleteable');
        }

        // Clean up - hard delete
        $this->entityManager->getFilters()
            ->disable('softdeleteable');
        try {
            $this->entityManager->remove($softDeletedTransfer);
            $this->entityManager->flush();
        } finally {
            $this->entityManager->getFilters()
                ->enable('softdeleteable');
        }
    }
}
