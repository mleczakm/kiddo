<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\RequestContext;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use App\Entity\UserConsent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Group('unit')]
final class UserConsentTest extends TestCase
{
    public function testStatementConsentSnapshotsRequestEvidenceAndCanBeRevoked(): void
    {
        $request = Request::create('/', server: [
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_USER_AGENT' => 'Kiddo Test Browser',
        ]);
        $stack = new RequestStack();
        $stack->push($request);
        $evidence = ConsentEvidence::statement('Zapisuję się na newsletter.', 'registration');

        $consent = new UserConsent(
            new User('parent@example.test', 'Parent'),
            ConsentType::MARKETING_EMAIL,
            ConsentSource::REGISTRATION,
            $evidence,
            new RequestContext($stack),
        );

        static::assertSame('192.0.2.10', $consent->getIp());
        static::assertSame('Kiddo Test Browser', $consent->getUserAgent());
        static::assertSame(hash('sha256', 'Zapisuję się na newsletter.'), $consent->getTextChecksum());
        static::assertTrue($consent->isActive());

        $revokedAt = new \DateTimeImmutable('2026-09-10 12:00:00');
        $consent->revoke($revokedAt);

        static::assertFalse($consent->isActive());
        static::assertSame($revokedAt, $consent->getRevokedAt());
    }

    public function testExternalWorkshopTermsAreValidEvidenceForClassesTerms(): void
    {
        $consent = new UserConsent(
            new User('parent@example.test', 'Parent'),
            ConsentType::CLASSES_TERMS,
            ConsentSource::BOOKING_MODAL,
            ConsentEvidence::externalDocument(
                'workshop:01HXYZ:terms',
                hash('sha256', 'workshop terms'),
                'Akceptuję regulamin zajęć.',
            ),
            new RequestContext(new RequestStack()),
        );

        static::assertNull($consent->getDocumentType());
        static::assertSame('workshop:01HXYZ:terms', $consent->getDocumentRef());
    }

    public function testAppTermsCannotBeRecordedAgainstPrivacyDocument(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UserConsent(
            new User('parent@example.test', 'Parent'),
            ConsentType::APP_TERMS,
            ConsentSource::REGISTRATION,
            ConsentEvidence::currentDocument(LegalDocumentType::PRIVACY, 'Akceptuję regulamin.'),
            new RequestContext(new RequestStack()),
        );
    }
}
