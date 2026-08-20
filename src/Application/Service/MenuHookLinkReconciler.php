<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\MenuHookLink;
use App\Entity\MenuHookLinkTarget;
use App\Entity\MenuHookSlot;
use App\Entity\Post;
use App\Repository\MenuHookLinkRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps an article's menu hook links (main nav / footer) in sync with the
 * slots selected on its edit form: creates a link for each newly selected
 * slot and removes links for slots that were deselected.
 */
final readonly class MenuHookLinkReconciler
{
    public function __construct(
        private MenuHookLinkRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<string> $selectedSlotKeys
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function reconcile(Post $post, array $selectedSlotKeys): void
    {
        $selectedSlots = array_values(array_filter(array_map(
            static fn(string $key): ?MenuHookSlot => MenuHookSlot::tryFrom($key),
            $selectedSlotKeys,
        )));
        $selectedKeys = array_map(static fn(MenuHookSlot $slot): string => $slot->value, $selectedSlots);

        $existingLinks = $this->repository->findForPostSlug($post->slug);
        $existingKeys = array_map(static fn(MenuHookLink $link): string => $link->getSlotKey(), $existingLinks);

        foreach ($existingLinks as $link) {
            if (!\in_array($link->getSlotKey(), $selectedKeys, true)) {
                $this->entityManager->remove($link);
            }
        }

        foreach ($selectedSlots as $slot) {
            if (\in_array($slot->value, $existingKeys, true)) {
                continue;
            }

            $link = new MenuHookLink(
                $slot->value,
                $this->repository->nextPositionForSlot($slot->value),
                MenuHookLinkTarget::POST,
                $post->slug,
                $post->body->getTitle(),
            );
            $this->entityManager->persist($link);
        }
    }
}
