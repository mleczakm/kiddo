<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\MenuHookLinkTarget;
use App\Entity\PostStatus;
use App\Repository\MenuHookLinkRepository;
use App\Repository\PostRepository;
use App\Repository\LessonMetadataRepository;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MenuHookExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuHookLinkRepository $menuHookLinkRepository,
        private readonly PostRepository $postRepository,
        private readonly LessonMetadataRepository $lessonMetadataRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_hook_links', $this->linksFor(...)),
        ];
    }

    /**
     * Resolve menu hook links for a slot into a list of {label, url} pairs.
     * Filters out unresolvable targets (unpublished posts, missing workshops, invalid URLs).
     *
     * @return list<array{label: string, url: string}>
     */
    public function linksFor(string $slotKey): array
    {
        $links = $this->menuHookLinkRepository->findActiveForSlot($slotKey);
        $result = [];

        $now = Clock::get()->now();

        foreach ($links as $link) {
            $url = match ($link->getTargetType()) {
                MenuHookLinkTarget::URL => $link->getTarget(),
                MenuHookLinkTarget::POST => $this->resolvePostUrl($link->getTarget(), $now),
                MenuHookLinkTarget::WORKSHOP => $this->resolveWorkshopUrl($link->getTarget()),
                default => null,
            };

            if ($url !== null) {
                $result[] = [
                    'label' => $link->getLabel(),
                    'url' => $url,
                ];
            }
        }

        return $result;
    }

    private function resolvePostUrl(string $postSlug, \DateTimeImmutable $now): ?string
    {
        $post = $this->postRepository->findOnePublishedBySlug($postSlug, $now);
        if ($post === null) {
            return null;
        }

        try {
            return $this->urlGenerator->generate('post_by_slug', ['slug' => $postSlug], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Exception) {
            return null;
        }
    }

    private function resolveWorkshopUrl(string $workshopSlug): ?string
    {
        if (!$this->lessonMetadataRepository->slugExists($workshopSlug)) {
            return null;
        }

        try {
            return $this->urlGenerator->generate('workshop_by_slug', ['slug' => $workshopSlug], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Exception) {
            return null;
        }
    }
}
