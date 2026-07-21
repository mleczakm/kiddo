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
        $metadata = $this->createMetadata('Senso Bobasy')
            ->withTitle('Bałaganki');

        $this->assertSame('Bałaganki', $metadata->title);
        $this->assertSame('balaganki', $metadata->slug);
    }

    public function testSlugifyIsDeterministicForSharedSeriesTitles(): void
    {
        $this->assertSame(LessonMetadata::slugify('Senso Bobasy'), LessonMetadata::slugify('Senso Bobasy'));
    }

    private function createMetadata(string $title, ?string $slug = null): LessonMetadata
    {
        return new LessonMetadata(
            title: $title,
            lead: 'Lead',
            visualTheme: '#fff',
            description: 'Description',
            capacity: 10,
            schedule: new \DateTimeImmutable('2024-02-20 10:00:00'),
            duration: 60,
            ageRange: new AgeRange(0, 3),
            category: 'test',
            slug: $slug,
        );
    }
}
