<?php

declare(strict_types=1);

namespace App\Tests\Application\Consent;

use App\Application\Consent\ConsentEvidence;
use App\Entity\LegalDocumentType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConsentEvidenceTest extends TestCase
{
    public function testStatementSnapshotsTheExactAcceptanceText(): void
    {
        $evidence = ConsentEvidence::statement('Wyrażam zgodę.', '  registration  ');

        static::assertSame(hash('sha256', 'Wyrażam zgodę.'), $evidence->textChecksum());
        static::assertSame('registration', $evidence->context());
        static::assertNull($evidence->requestedDocumentType());
        static::assertNull($evidence->documentChecksum());
    }

    public function testCurrentDocumentKeepsTheRequestedDocumentType(): void
    {
        $evidence = ConsentEvidence::currentDocument(LegalDocumentType::APP_TERMS, 'Akceptuję regulamin.');

        static::assertSame(LegalDocumentType::APP_TERMS, $evidence->requestedDocumentType());
        static::assertNull($evidence->documentVersion());
    }

    public function testExternalDocumentRequiresReferenceAndValidChecksum(): void
    {
        $evidence = ConsentEvidence::externalDocument(
            'workshop:123:terms',
            hash('sha256', 'document'),
            'Akceptuję regulamin zajęć.',
        );

        static::assertSame('workshop:123:terms', $evidence->documentRef());
        static::assertSame(hash('sha256', 'document'), $evidence->documentChecksum());
    }

    public function testBlankAcceptanceTextIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConsentEvidence::statement('  ');
    }

    public function testMalformedExternalChecksumIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConsentEvidence::externalDocument('workshop:123:terms', 'not-a-checksum', 'Akceptuję.');
    }
}
