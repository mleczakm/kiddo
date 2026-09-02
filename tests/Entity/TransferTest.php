<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Transfer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class TransferTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testTheSameMessageIdCannotBeInsertedTwice(): void
    {
        $messageId = 'dup-' . uniqid('', true) . '@alior.pl';

        $first = new Transfer('111', 'Sender A', 'Title A', '10.00', new \DateTimeImmutable());
        $first->setMessageId($messageId);
        $this->entityManager->persist($first);
        $this->entityManager->flush();

        $second = new Transfer('222', 'Sender B', 'Title B', '20.00', new \DateTimeImmutable());
        $second->setMessageId($messageId);
        $this->entityManager->persist($second);

        $violated = false;
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $violated = true;
        }

        static::assertTrue($violated, 'A second transfer with the same message_id must be rejected by the DB');

        // The failed flush closes the EntityManager - reset it before cleaning up.
        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get('doctrine');
        $registry->resetManager();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->entityManager->getConnection()->executeStatement('DELETE FROM transfer WHERE message_id = :messageId', [
            'messageId' => $messageId,
        ]);
    }

    public function testTransfersWithoutAMessageIdDoNotCollide(): void
    {
        $one = new Transfer('333', 'Sender C', 'Title C', '30.00', new \DateTimeImmutable());
        $two = new Transfer('444', 'Sender D', 'Title D', '40.00', new \DateTimeImmutable());

        $this->entityManager->persist($one);
        $this->entityManager->persist($two);
        $this->entityManager->flush();

        static::assertNull($one->getMessageId());
        static::assertNull($two->getMessageId());

        $this->entityManager->remove($one);
        $this->entityManager->remove($two);
        $this->entityManager->flush();
    }

    public function testSoftDelete(): void
    {
        $transfer = new Transfer(
            '123456789',
            'Test Sender',
            'Test Title',
            '100.00',
            new \DateTimeImmutable('2024-01-01'),
        );

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        // Verify transfer exists before soft delete
        $foundTransfer = $this->entityManager->find(Transfer::class, $transferId);
        static::assertNotNull($foundTransfer);
        static::assertNull($foundTransfer->getDeletedAt());

        // Soft delete the transfer
        $this->entityManager->remove($transfer);
        $this->entityManager->flush();

        // Clear entity manager to ensure fresh query
        $this->entityManager->clear();

        // Transfer should not be found with regular find (soft delete filter active)
        $deletedTransfer = $this->entityManager->find(Transfer::class, $transferId);
        static::assertNull($deletedTransfer, 'Transfer should not be found when soft delete filter is active');

        // But should be found when soft delete filter is disabled
        $this->entityManager->getFilters()->disable('softdeleteable');
        try {
            $softDeletedTransfer = $this->entityManager->find(Transfer::class, $transferId);
            static::assertNotNull($softDeletedTransfer, 'Transfer should be found when soft delete filter is disabled');
            static::assertNotNull($softDeletedTransfer->getDeletedAt(), 'deletedAt should be set');
        } finally {
            $this->entityManager->getFilters()->enable('softdeleteable');
        }

        // Clean up - hard delete
        $this->entityManager->getFilters()->disable('softdeleteable');
        try {
            $this->entityManager->remove($softDeletedTransfer);
            $this->entityManager->flush();
        } finally {
            $this->entityManager->getFilters()->enable('softdeleteable');
        }
    }
}
