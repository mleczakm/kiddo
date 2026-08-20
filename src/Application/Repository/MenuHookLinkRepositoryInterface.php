<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\MenuHookLink;

/**
 * @extends RepositoryInterface<MenuHookLink>
 */
interface MenuHookLinkRepositoryInterface extends RepositoryInterface
{
    /** @return list<MenuHookLink> */
    public function findActiveForSlot(string $slotKey): array;

    /** @return list<MenuHookLink> */
    public function findForPostSlug(string $slug): array;

    public function nextPositionForSlot(string $slotKey): int;
}
