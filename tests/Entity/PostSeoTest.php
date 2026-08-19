<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\PostSeo;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PostSeoTest extends TestCase
{
    public function testDefaultsToIndexableMetadataWithContentFallbacks(): void
    {
        $seo = new PostSeo();

        static::assertNull($seo->getSeoTitle());
        static::assertNull($seo->getSeoDescription());
        static::assertNull($seo->getCanonicalUrl());
        static::assertTrue($seo->shouldRobotsIndex());
        static::assertTrue($seo->shouldRobotsFollow());
        static::assertNull($seo->getSocialTitle());
        static::assertNull($seo->getSocialDescription());
    }
}
