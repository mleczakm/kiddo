<?php

declare(strict_types=1);

namespace App\Tests\Application\File;

use App\Application\File\OrphanFileCleanupService;
use App\Entity\File;
use App\Entity\Post;
use App\Entity\PostFile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class OrphanFileCleanupServiceTest extends KernelTestCase
{
    private OrphanFileCleanupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = static::getContainer()->get(OrphanFileCleanupService::class);
    }

    public function testCountOrphansIncludesUnreferencedFiles(): void
    {
        $em = $this->getEntityManager();

        $orphan = new File('orphan.jpg', 'image/jpeg', 1000, str_repeat('a', 64), base64_encode('test'));
        $em->persist($orphan);
        $em->flush();

        $count = $this->service->countOrphans();
        static::assertGreaterThanOrEqual(1, $count);
    }

    public function testCountOrphansExcludesReferencedFiles(): void
    {
        $em = $this->getEntityManager();

        $author = new User('author@example.com', 'Author');
        $post = new Post('Article', $author);
        $file = new File('attached.jpg', 'image/jpeg', 1000, str_repeat('b', 64), base64_encode('test'));

        $em->persist($author);
        $em->persist($post);
        $em->persist($file);
        $em->flush();

        $postFile = new PostFile($post, $file);
        $em->persist($postFile);
        $em->flush();

        $orphans = $this->service->countOrphans();
        static::assertSame(0, $orphans);
    }

    public function testCleanupDeletesOldOrphanedFiles(): void
    {
        $em = $this->getEntityManager();

        $oldOrphan = new File('old.jpg', 'image/jpeg', 1000, str_repeat('c', 64), base64_encode('test'));
        $this->setFileCreatedAt($oldOrphan, Clock::get()->now()->modify('-48 hours'));
        $em->persist($oldOrphan);
        $em->flush();

        $oldId = $oldOrphan->getId();

        $deleted = $this->service->cleanup();
        static::assertGreaterThan(0, $deleted);

        // The DQL bulk DELETE in cleanup() operates at the SQL level and
        // doesn't update the EntityManager's identity map, so find() would
        // otherwise return the stale in-memory object instead of re-querying.
        $em->clear();

        $found = $em->find(File::class, $oldId);
        static::assertNull($found, 'Old orphaned file should be deleted');
    }

    public function testCleanupKeepsRecentOrphanedFiles(): void
    {
        $em = $this->getEntityManager();

        $recent = new File('recent.jpg', 'image/jpeg', 1000, str_repeat('d', 64), base64_encode('test'));
        $em->persist($recent);
        $em->flush();

        $recentId = $recent->getId();

        $this->service->cleanup();

        $found = $em->find(File::class, $recentId);
        static::assertNotNull($found, 'Recent orphaned file should be kept');
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function setFileCreatedAt(File $file, \DateTimeImmutable $createdAt): void
    {
        $reflection = new \ReflectionClass($file);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($file, $createdAt);
    }
}
