<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Factory\LegalDocumentFactory;
use App\Factory\LegalDocumentVersionFactory;
use App\Factory\UserConsentFactory;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class LegalConsentFactoriesTest extends KernelTestCase
{
    public function testLegalDocumentFactoriesCreateACompleteVersion(): void
    {
        self::bootKernel();

        $document = LegalDocumentFactory::new()->privacy()->create();
        $version = LegalDocumentVersionFactory::createOne([
            'document' => $document,
            'changeSummary' => 'Initial privacy policy',
        ]);

        static::assertSame(LegalDocumentType::PRIVACY, $version->getDocument()->getType());
        static::assertSame(1, $version->getVersion());
        static::assertSame($version->getFile()->getChecksum(), $version->getChecksum());
    }

    public function testUserConsentFactoryCreatesStandaloneAndVersionedEvidence(): void
    {
        self::bootKernel();

        $marketing = UserConsentFactory::createOne();
        $version = LegalDocumentVersionFactory::createOne();
        $terms = UserConsentFactory::new()->forDocument($version, 'I accept the terms.')->create();

        static::assertSame(ConsentType::MARKETING_EMAIL, $marketing->getType());
        static::assertNull($marketing->getDocumentVersion());
        static::assertSame(ConsentType::APP_TERMS, $terms->getType());
        static::assertSame($version->getId(), $terms->getDocumentVersion()?->getId());
    }
}
