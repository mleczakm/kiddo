<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Repository;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\Notification;
use App\Entity\NotificationSeverity;
use App\Entity\Tenant;
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
        $tenant = new Tenant('Kiddo', 'repo.test');
        $user = new User('repo@example.com', 'Repo User');
        $this->persist($tenant);
        $this->persist($user);

        $n1 = new Notification($tenant, $user, 'First', null, null, NotificationSeverity::Info); // unread
        $this->persist($n1);
        // older
        $nOld = new Notification($tenant, $user, 'Old', null, null, NotificationSeverity::Warning);
        // manually backdate
        $ref = new \ReflectionProperty(Notification::class, 'createdAt');
        $ref->setValue($nOld, (new \DateTimeImmutable('-2 days')));
        $this->persist($nOld);

        $nRead = new Notification($tenant, $user, 'Read', null, null, NotificationSeverity::Success);
        $this->persist($nRead);
        $nRead->markRead();
        $this->em->flush();

        $nDeleted = new Notification($tenant, $user, 'Deleted', null, null, NotificationSeverity::Error);
        $this->persist($nDeleted);
        $nDeleted->softDelete();
        $this->em->flush();

        self::assertSame(2, $this->repo->countUnreadForUser($user)); // n1 + nOld

        $recent = $this->repo->findRecentForUser($user, 10);
        self::assertCount(3, $recent); // deleted filtered out
        // Order by createdAt desc: last persisted (Deleted) will have now but removed; so nRead, n1, nOld
        self::assertSame('First', $recent[0]->getTitle());
        self::assertSame('Read', $recent[1]->getTitle());
        self::assertSame('Old', $recent[2]->getTitle());
    }
}
