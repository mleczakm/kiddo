<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\MenuHookLink;
use App\Entity\MenuHookLinkTarget;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class MenuHookLinkTest extends TestCase
{
    public function testKeepsMenuPlacementAndTarget(): void
    {
        $link = new MenuHookLink('footer_links', 2, MenuHookLinkTarget::POST, 'fluo-party', 'Fluo Party');

        static::assertSame('footer_links', $link->getSlotKey());
        static::assertSame(2, $link->getPosition());
        static::assertSame(MenuHookLinkTarget::POST, $link->getTargetType());
        static::assertTrue($link->isActive());
    }
}
