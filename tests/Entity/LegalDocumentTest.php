<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\File;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class LegalDocumentTest extends TestCase
{
    public function testPublishedVersionsAreNumberedAndSnapshotTheFileChecksum(): void
    {
        $document = new LegalDocument(LegalDocumentType::APP_TERMS);
        $publisher = new User('publisher@example.test', 'Publisher');
        $firstFile = $this->file('first.pdf', 'first');
        $secondFile = $this->file('second.pdf', 'second');

        $first = new LegalDocumentVersion(
            $document,
            $firstFile,
            new \DateTimeImmutable('2026-09-10'),
            $publisher,
            'Initial publication',
        );
        $second = new LegalDocumentVersion($document, $secondFile, new \DateTimeImmutable('2026-10-01'), $publisher);

        static::assertSame(1, $first->getVersion());
        static::assertSame(2, $second->getVersion());
        static::assertSame($firstFile->getChecksum(), $first->getChecksum());
        static::assertSame('regulamin', $document->getSlug());
        static::assertCount(2, $document->getVersions());
    }

    public function testChangeSummaryIsNormalized(): void
    {
        $version = new LegalDocumentVersion(
            new LegalDocument(LegalDocumentType::PRIVACY),
            $this->file('privacy.pdf', 'privacy'),
            new \DateTimeImmutable('2026-09-10'),
            new User('publisher@example.test', 'Publisher'),
            '  ',
        );

        static::assertNull($version->getChangeSummary());
    }

    private function file(string $name, string $contents): File
    {
        return new File(
            $name,
            'application/pdf',
            \strlen($contents),
            hash('sha256', $contents),
            base64_encode($contents),
        );
    }
}
