<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\MenuHookLinkTarget;
use App\Entity\PostFileRole;
use App\Entity\PostStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CmsEnumTest extends TestCase
{
    public function testPersistedEnumValuesRemainStable(): void
    {
        static::assertSame(['draft', 'published'], array_column(PostStatus::cases(), 'value'));
        static::assertSame(['cover', 'inline', 'attachment'], array_column(PostFileRole::cases(), 'value'));
        static::assertSame(['post', 'workshop', 'url'], array_column(MenuHookLinkTarget::cases(), 'value'));
    }
}
