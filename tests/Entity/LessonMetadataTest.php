<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AgeRange;
use App\Entity\LessonMetadata;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class LessonMetadataTest extends TestCase
{
    public function testConstructorGeneratesSlugFromTitle(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy');

        $this->assertSame('senso-bobasy', $metadata->slug);
    }

    public function testConstructorTransliteratesPolishCharactersInSlug(): void
    {
        $metadata = $this->createMetadata('Bałaganki');

        $this->assertSame('balaganki', $metadata->slug);
    }

    public function testConstructorKeepsExplicitSlug(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy', 'custom-slug');

        $this->assertSame('custom-slug', $metadata->slug);
    }

    public function testConstructorReplacesEmptySlug(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy', '');

        $this->assertSame('senso-bobasy', $metadata->slug);
    }

    public function testWithTitleRegeneratesSlug(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy')->withTitle('Bałaganki');

        $this->assertSame('Bałaganki', $metadata->title);
        $this->assertSame('balaganki', $metadata->slug);
    }

    public function testSlugifyIsDeterministicForSharedSeriesTitles(): void
    {
        $this->assertSame(LessonMetadata::slugify('Senso Bobasy'), LessonMetadata::slugify('Senso Bobasy'));
    }

    public function testGetSafeVisualThemeKeepsValidHexColor(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy', visualTheme: '#a1b2c3');

        $this->assertSame('#a1b2c3', $metadata->getSafeVisualTheme());
    }

    public function testGetSafeVisualThemeFallsBackForLegacyFreeTextValue(): void
    {
        // Pre-fix rows may hold a Tailwind-style token like "orange-100",
        // which is not valid CSS for background-color.
        $metadata = $this->createMetadata('Senso Bobasy', visualTheme: 'orange-100');

        $this->assertSame(LessonMetadata::DEFAULT_VISUAL_THEME, $metadata->getSafeVisualTheme());
    }

    private function createMetadata(string $title, ?string $slug = null, string $visualTheme = '#fff'): LessonMetadata
    {
        return new LessonMetadata(
            title: $title,
            lead: 'Lead',
            visualTheme: $visualTheme,
            description: 'Description',
            capacity: 10,
            duration: 60,
            ageRange: new AgeRange(0, 3),
            category: 'test',
            slug: $slug,
        );
    }
}
