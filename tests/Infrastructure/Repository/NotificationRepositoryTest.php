<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Repository;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\Notification;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class NotificationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private NotificationRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = self::getContainer()->get(NotificationRepository::class);
    }

    private function persist(object $entity): void
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function testCountUnreadAndFindRecentFiltersAndOrders(): void
    {
        $user = new User('repo@example.com', 'Repo User');
        $this->persist($user);

        $createdAt = new \ReflectionProperty(Notification::class, 'createdAt');
        $base = new \DateTimeImmutable('2025-06-15 12:00:00');

        $n1 = new Notification($user, 'First', null, null, NotificationSeverity::Info); // unread
        $createdAt->setValue($n1, $base);
        $this->persist($n1);

        $nOld = new Notification($user, 'Old', null, null, NotificationSeverity::Warning);
        $createdAt->setValue($nOld, $base->modify('-2 days'));
        $this->persist($nOld);

        $nRead = new Notification($user, 'Read', null, null, NotificationSeverity::Success);
        $createdAt->setValue($nRead, $base->modify('+1 hour'));
        $this->persist($nRead);
        $nRead->markRead();
        $this->em->flush();

        $nDeleted = new Notification($user, 'Deleted', null, null, NotificationSeverity::Error);
        $createdAt->setValue($nDeleted, $base->modify('+2 hours'));
        $this->persist($nDeleted);
        $nDeleted->softDelete();
        $this->em->flush();

        self::assertSame(2, $this->repo->countUnreadForUser($user)); // n1 + nOld

        $recent = $this->repo->findRecentForUser($user, 10);
        self::assertCount(3, $recent); // deleted filtered out
        self::assertSame('Read', $recent[0]->getTitle());
        self::assertSame('First', $recent[1]->getTitle());
        self::assertSame('Old', $recent[2]->getTitle());
    }
}
