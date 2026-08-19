<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\MenuHookLink;
use App\Entity\MenuHookLinkTarget;
use App\Entity\Post;
use App\Entity\PostStatus;
use App\Entity\User;
use App\Repository\LessonMetadataRepository;
use App\Repository\MenuHookLinkRepository;
use App\Repository\PostRepository;
use App\Twig\MenuHookExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
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
        $postRepository->expects(static::once())
            ->method('findOnePublishedBySlug')
            ->with('unpublished-article', static::anything())
            ->willReturn(null);

        $extension = new MenuHookExtension(
            $this->createMockRepository([$mockLink]),
            $postRepository,
            $this->createMock(LessonMetadataRepository::class),
            $this->createMock(UrlGeneratorInterface::class),
        );

        $links = $extension->linksFor('main_nav_extra');

        static::assertCount(0, $links);
    }

    public function testLinksForFiltersOutNonexistentWorkshops(): void
    {
        $mockLink = new MenuHookLink('main_nav_extra', 0, MenuHookLinkTarget::WORKSHOP, 'nonexistent-workshop', 'Workshop');
        $workshopRepository = $this->createMock(LessonMetadataRepository::class);
        $workshopRepository->expects(static::once())
            ->method('slugExists')
            ->with('nonexistent-workshop')
            ->willReturn(false);

        $extension = new MenuHookExtension(
            $this->createMockRepository([$mockLink]),
            $this->createMock(PostRepository::class),
            $workshopRepository,
            $this->createMock(UrlGeneratorInterface::class),
        );

        $links = $extension->linksFor('main_nav_extra');

        static::assertCount(0, $links);
    }

    private function createMockRepository(array $links): MenuHookLinkRepository
    {
        $repository = $this->createMock(MenuHookLinkRepository::class);
        $repository->expects(static::any())
            ->method('findActiveForSlot')
            ->willReturn($links);

        return $repository;
    }
}
