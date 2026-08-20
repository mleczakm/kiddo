<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\MenuHookLink;
use App\Entity\MenuHookLinkTarget;
use App\Entity\Post;
use App\Entity\PostVisibility;
use App\Entity\User;
use App\Repository\LessonMetadataRepository;
use App\Repository\MenuHookLinkRepository;
use App\Repository\PostRepository;
use App\Twig\MenuHookExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MenuHookExtensionTest extends TestCase
{
    public function testLinksForReturnsEmptyListWhenNoLinksConfigured(): void
    {
        $extension = new MenuHookExtension(
            $this->createMockRepository([]),
            $this->createMock(PostRepository::class),
            $this->createMock(LessonMetadataRepository::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Security::class),
        );

        $links = $extension->linksFor('main_nav_extra');

        static::assertSame([], $links);
    }

    public function testLinksForReturnsUrlTargetsAsIs(): void
    {
        $mockLink = new MenuHookLink('main_nav_extra', 0, MenuHookLinkTarget::URL, 'https://example.com', 'Example');
        $extension = new MenuHookExtension(
            $this->createMockRepository([$mockLink]),
            $this->createMock(PostRepository::class),
            $this->createMock(LessonMetadataRepository::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Security::class),
        );

        $links = $extension->linksFor('main_nav_extra');

        static::assertCount(1, $links);
        static::assertSame('Example', $links[0]['label']);
        static::assertSame('https://example.com', $links[0]['url']);
    }

    public function testLinksForFiltersOutUnpublishedPosts(): void
    {
        $mockLink = new MenuHookLink('main_nav_extra', 0, MenuHookLinkTarget::POST, 'unpublished-article', 'Article');
        $postRepository = $this->createMock(PostRepository::class);
        $postRepository
            ->expects(static::once())
            ->method('findOnePublishedBySlug')
            ->with('unpublished-article', static::anything())
            ->willReturn(null);

        $extension = new MenuHookExtension(
            $this->createMockRepository([$mockLink]),
            $postRepository,
            $this->createMock(LessonMetadataRepository::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Security::class),
        );

        $links = $extension->linksFor('main_nav_extra');

        static::assertCount(0, $links);
    }

    public function testLinksForFiltersOutNonexistentWorkshops(): void
    {
        $mockLink = new MenuHookLink(
            'main_nav_extra',
            0,
            MenuHookLinkTarget::WORKSHOP,
            'nonexistent-workshop',
            'Workshop',
        );
        $workshopRepository = $this->createMock(LessonMetadataRepository::class);
        $workshopRepository
            ->expects(static::once())
            ->method('slugExists')
            ->with('nonexistent-workshop')
            ->willReturn(false);

        $extension = new MenuHookExtension(
            $this->createMockRepository([$mockLink]),
            $this->createMock(PostRepository::class),
            $workshopRepository,
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Security::class),
        );

        $links = $extension->linksFor('main_nav_extra');

        static::assertCount(0, $links);
    }

    public function testLinksForFiltersOutPostsNotVisibleToViewer(): void
    {
        $mockLink = new MenuHookLink('main_nav_extra', 0, MenuHookLinkTarget::POST, 'staff-only-article', 'Article');
        $post = new Post('Staff-only article', new User('author@example.test', 'Author'), 'staff-only-article');
        $post->setVisibility(PostVisibility::STAFF_ONLY);

        $postRepository = $this->createMock(PostRepository::class);
        $postRepository
            ->method('findOnePublishedBySlug')
            ->with('staff-only-article', static::anything())
            ->willReturn($post);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);
        $security->method('isGranted')->with('ROLE_HOST')->willReturn(false);

        $extension = new MenuHookExtension(
            $this->createMockRepository([$mockLink]),
            $postRepository,
            $this->createMock(LessonMetadataRepository::class),
            $this->createMock(UrlGeneratorInterface::class),
            $security,
        );

        $links = $extension->linksFor('main_nav_extra');

        static::assertCount(0, $links);
    }

    public function testLinksForIncludesPostsVisibleToViewer(): void
    {
        $mockLink = new MenuHookLink('main_nav_extra', 0, MenuHookLinkTarget::POST, 'staff-only-article', 'Article');
        $post = new Post('Staff-only article', new User('author@example.test', 'Author'), 'staff-only-article');
        $post->setVisibility(PostVisibility::STAFF_ONLY);

        $postRepository = $this->createMock(PostRepository::class);
        $postRepository
            ->method('findOnePublishedBySlug')
            ->with('staff-only-article', static::anything())
            ->willReturn($post);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new User('staff@example.test', 'Staff'));
        $security->method('isGranted')->with('ROLE_HOST')->willReturn(true);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://kiddo.test/blog/staff-only-article');

        $extension = new MenuHookExtension(
            $this->createMockRepository([$mockLink]),
            $postRepository,
            $this->createMock(LessonMetadataRepository::class),
            $urlGenerator,
            $security,
        );

        $links = $extension->linksFor('main_nav_extra');

        static::assertCount(1, $links);
        static::assertSame('https://kiddo.test/blog/staff-only-article', $links[0]['url']);
    }

    private function createMockRepository(array $links): MenuHookLinkRepository
    {
        $repository = $this->createMock(MenuHookLinkRepository::class);
        $repository->expects(static::any())->method('findActiveForSlot')->willReturn($links);

        return $repository;
    }
}
