<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\PostBody;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PostBodyTest extends TestCase
{
    public function testDefaultsToAnEmptyTiptapDocument(): void
    {
        $body = new PostBody('Article title');

        static::assertSame('Article title', $body->getTitle());
        static::assertSame(['type' => 'doc', 'content' => []], $body->getContentJson());
        static::assertNull($body->getContentHtml());
        static::assertNull($body->getLinkedWorkshopSlug());
        static::assertNull($body->getEyebrow());
        static::assertNull($body->getExcerpt());
    }
}
