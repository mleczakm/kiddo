<?php

declare(strict_types=1);

namespace App\Tests\Application\File;

use App\Application\File\OrphanFileCleanupService;
use App\Entity\File;
use App\Entity\Post;
use App\Entity\PostFile;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

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
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $orphan = new File('orphan.jpg', 'image/jpeg', 1000, str_repeat('a', 64), base64_encode('test'));
        $em->persist($orphan);
        $em->flush();

        $count = $this->service->countOrphans();
        static::assertGreaterThanOrEqual(1, $count);
    }

    public function testCountOrphansExcludesReferencedFiles(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

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
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $oldOrphan = new File('old.jpg', 'image/jpeg', 1000, str_repeat('c', 64), base64_encode('test'));
        $this->setFileCreatedAt($oldOrphan, Clock::get()->now()->modify('-48 hours'));
        $em->persist($oldOrphan);
        $em->flush();

        $oldId = $oldOrphan->getId();

        $deleted = $this->service->cleanup();
        static::assertGreaterThan(0, $deleted);

        $found = $em->find(File::class, $oldId);
        static::assertNull($found, 'Old orphaned file should be deleted');
    }

    public function testCleanupKeepsRecentOrphanedFiles(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $recent = new File('recent.jpg', 'image/jpeg', 1000, str_repeat('d', 64), base64_encode('test'));
        $em->persist($recent);
        $em->flush();

        $recentId = $recent->getId();

        $this->service->cleanup();

        $found = $em->find(File::class, $recentId);
        static::assertNotNull($found, 'Recent orphaned file should be kept');
    }

    private function setFileCreatedAt(File $file, \DateTimeImmutable $createdAt): void
    {
        $reflection = new \ReflectionClass($file);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($file, $createdAt);
    }
}
