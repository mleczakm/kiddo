<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MessageStatus;
use App\Entity\MessageType;
use App\Entity\User;
use App\Entity\UserMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserMessage>
 */
class UserMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserMessage::class);
    }

    /**
     * @return array<UserMessage>
     */
    public function findUnreadMessages(): array
    {
        /** @var array<UserMessage> $messages */
        $messages = $this->createQueryBuilder('um')
            ->where('um.status = :status')
            ->setParameter('status', MessageStatus::UNREAD)
            ->orderBy('um.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $messages;
    }

    /**
     * @return array<UserMessage>
     */
    public function findRecentMessages(int $limit = 10): array
    {
        /** @var array<UserMessage> $messages */
        $messages = $this->createQueryBuilder('um')
            ->orderBy('um.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $messages;
    }

    /**
     * @return array<UserMessage>
     */
    public function findByStatus(MessageStatus $status): array
    {
        /** @var array<UserMessage> $messages */
        $messages = $this->createQueryBuilder('um')
            ->where('um.status = :status')
            ->setParameter('status', $status)
            ->orderBy('um.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $messages;
    }

    /**
     * @return array<UserMessage>
     */
    public function findByType(MessageType $type): array
    {
        /** @var array<UserMessage> $messages */
        $messages = $this->createQueryBuilder('um')
            ->where('um.type = :type')
            ->setParameter('type', $type)
            ->orderBy('um.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $messages;
    }

    public function countUnreadMessages(): int
    {
        return (int) $this->createQueryBuilder('um')
            ->select('COUNT(um.id)')
            ->where('um.status = :status')
            ->setParameter('status', MessageStatus::UNREAD)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<UserMessage>
     */
    public function findByUser(User $user): array
    {
        /** @var array<UserMessage> $messages */
        $messages = $this->createQueryBuilder('um')
            ->where('um.user = :user')
            ->setParameter('user', $user)
            ->orderBy('um.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $messages;
    }
}
