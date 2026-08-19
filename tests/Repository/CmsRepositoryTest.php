<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\MenuHookLink;
use App\Entity\MenuHookLinkTarget;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\MenuHookLinkRepository;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class CmsRepositoryTest extends KernelTestCase
{
    public function testFindsOnlyPostPublishedByRequestedTime(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $author = new User('cms-author@example.com', 'CMS Author');
        $published = new Post('Published', $author, 'published');
        $published->publishAt(new \DateTimeImmutable('2026-08-19T09:00:00+02:00'));
        $scheduled = new Post('Scheduled', $author, 'scheduled');
        $scheduled->publishAt(new \DateTimeImmutable('2026-08-20T09:00:00+02:00'));
        $draft = new Post('Draft', $author, 'draft');

        $entityManager->persist($author);
        $entityManager->persist($published);
        $entityManager->persist($scheduled);
        $entityManager->persist($draft);
        $entityManager->flush();

        $repository = self::getContainer()->get(PostRepository::class);
        static::assertInstanceOf(PostRepository::class, $repository);
        static::assertSame(
            $published->getId(),
            $repository
                ->findOnePublishedBySlug('published', new \DateTimeImmutable('2026-08-19T10:00:00+02:00'))
                ?->getId(),
        );
        static::assertNull($repository->findOnePublishedBySlug(
            'scheduled',
            new \DateTimeImmutable('2026-08-19T10:00:00+02:00'),
        ));
        static::assertNull($repository->findOnePublishedBySlug(
            'draft',
            new \DateTimeImmutable('2026-08-19T10:00:00+02:00'),
        ));
    }

    public function testMenuLinksAreOrderedWithinSlot(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $later = new MenuHookLink('footer_links', 20, MenuHookLinkTarget::URL, '/later', 'Later');
        $first = new MenuHookLink('footer_links', 10, MenuHookLinkTarget::URL, '/first', 'First');
        $otherSlot = new MenuHookLink('main_nav_extra', 0, MenuHookLinkTarget::URL, '/other', 'Other');
        $entityManager->persist($later);
        $entityManager->persist($first);
        $entityManager->persist($otherSlot);
        $entityManager->flush();

        $repository = self::getContainer()->get(MenuHookLinkRepository::class);
        static::assertInstanceOf(MenuHookLinkRepository::class, $repository);
        $links = $repository->findActiveForSlot('footer_links');

        static::assertSame(
            [$first->getId(), $later->getId()],
            array_map(static fn(MenuHookLink $link) => $link->getId(), $links),
        );
    }
}
