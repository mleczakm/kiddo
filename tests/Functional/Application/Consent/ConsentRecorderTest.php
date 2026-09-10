<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Consent;

use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Application\Consent\ConsentRecorder;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\File;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Group('functional')]
final class ConsentRecorderTest extends KernelTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testRecordsAndRevokesStatementConsentWithRequestEvidence(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var RequestStack $requestStack */
        $requestStack = $container->get(RequestStack::class);
        $requestStack->push(Request::create('/', server: [
            'REMOTE_ADDR' => '198.51.100.25',
            'HTTP_USER_AGENT' => 'Functional Test Browser',
        ]));
        $user = $this->persistUser($entityManager);
        /** @var ConsentRecorder $recorder */
        $recorder = $container->get(ConsentRecorder::class);

        $consent = $recorder->record(
            $user,
            ConsentType::MARKETING_EMAIL,
            ConsentSource::REGISTRATION,
            ConsentEvidence::statement('Chcę otrzymywać wiadomości marketingowe.', 'register'),
        );

        static::assertNotNull($consent);
        static::assertSame('198.51.100.25', $consent->getIp());
        static::assertSame('Functional Test Browser', $consent->getUserAgent());
        static::assertTrue($recorder->hasCurrent($user, ConsentType::MARKETING_EMAIL));
        static::assertTrue($recorder->revoke($user, ConsentType::MARKETING_EMAIL, ConsentSource::PROFILE));
        static::assertFalse($recorder->hasCurrent($user, ConsentType::MARKETING_EMAIL));
        $requestStack->pop();
    }

    public function testResolvesCurrentLegalVersionAndDetectsOutdatedConsent(): void
    {
        Clock::set(new MockClock('2026-09-10 12:00:00'));
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $user = $this->persistUser($entityManager);
        $version = $this->persistLegalVersion($entityManager, $user, LegalDocumentType::APP_TERMS);
        $this->persistLegalVersion($entityManager, $user, LegalDocumentType::PRIVACY);
        /** @var ConsentRecorder $recorder */
        $recorder = $container->get(ConsentRecorder::class);

        static::assertEqualsCanonicalizing(
            [LegalDocumentType::APP_TERMS, LegalDocumentType::PRIVACY],
            $recorder->outdatedDocuments($user),
        );

        $consent = $recorder->record(
            $user,
            ConsentType::APP_TERMS,
            ConsentSource::REGISTRATION,
            ConsentEvidence::currentDocument(LegalDocumentType::APP_TERMS, 'Akceptuję regulamin aplikacji.'),
        );

        static::assertNotNull($consent);
        static::assertTrue($consent->getDocumentVersion()?->getId()->equals($version->getId()));
        static::assertTrue($recorder->hasCurrent($user, ConsentType::APP_TERMS));
        static::assertSame([LegalDocumentType::PRIVACY], $recorder->outdatedDocuments($user));
    }

    public function testMissingCurrentDocumentDoesNotCreateConsent(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $user = $this->persistUser($entityManager);
        /** @var ConsentRecorder $recorder */
        $recorder = $container->get(ConsentRecorder::class);

        $consent = $recorder->record(
            $user,
            ConsentType::PRIVACY,
            ConsentSource::REGISTRATION,
            ConsentEvidence::currentDocument(LegalDocumentType::PRIVACY, 'Akceptuję politykę prywatności.'),
        );

        static::assertNull($consent);
        /** @var UserConsentRepository $repository */
        $repository = $container->get(UserConsentRepository::class);
        static::assertSame([], $repository->findHistoryForUser($user));
    }

    public function testRecordsACompleteConsentSetInOneOperation(): void
    {
        Clock::set(new MockClock('2026-09-10 12:00:00'));
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $user = $this->persistUser($entityManager);
        $this->persistLegalVersion($entityManager, $user, LegalDocumentType::APP_TERMS);
        $this->persistLegalVersion($entityManager, $user, LegalDocumentType::PRIVACY);
        /** @var ConsentRecorder $recorder */
        $recorder = $container->get(ConsentRecorder::class);

        $consents = $recorder->recordMany(
            $user,
            ConsentSource::REGISTRATION,
            new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                LegalDocumentType::APP_TERMS,
                'Akceptuję dokumenty.',
            )),
            new ConsentGrant(ConsentType::PRIVACY, ConsentEvidence::currentDocument(
                LegalDocumentType::PRIVACY,
                'Akceptuję dokumenty.',
            )),
        );

        static::assertCount(2, $consents);
        static::assertSame(ConsentType::APP_TERMS, $consents[0]->getType());
        static::assertSame(ConsentType::PRIVACY, $consents[1]->getType());
    }

    public function testDoesNotPersistAPartialSetWhenOneDocumentIsMissing(): void
    {
        Clock::set(new MockClock('2026-09-10 12:00:00'));
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $user = $this->persistUser($entityManager);
        $this->persistLegalVersion($entityManager, $user, LegalDocumentType::APP_TERMS);
        /** @var ConsentRecorder $recorder */
        $recorder = $container->get(ConsentRecorder::class);

        $consents = $recorder->recordMany(
            $user,
            ConsentSource::REGISTRATION,
            new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                LegalDocumentType::APP_TERMS,
                'Akceptuję dokumenty.',
            )),
            new ConsentGrant(ConsentType::PRIVACY, ConsentEvidence::currentDocument(
                LegalDocumentType::PRIVACY,
                'Akceptuję dokumenty.',
            )),
        );

        static::assertSame([], $consents);
        /** @var UserConsentRepository $repository */
        $repository = $container->get(UserConsentRepository::class);
        static::assertSame([], $repository->findHistoryForUser($user));
    }

    private function persistUser(EntityManagerInterface $entityManager): User
    {
        $user = new User(sprintf('consent-%s@example.test', bin2hex(random_bytes(6))), 'Consent Tester');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function persistLegalVersion(
        EntityManagerInterface $entityManager,
        User $publisher,
        LegalDocumentType $type,
    ): LegalDocumentVersion {
        $contents = sprintf('%s document contents', $type->value);
        $file = new File(
            sprintf('%s.pdf', $type->value),
            'application/pdf',
            \strlen($contents),
            hash('sha256', $contents),
            base64_encode($contents),
        );
        $document = new LegalDocument($type);
        $version = new LegalDocumentVersion(
            $document,
            $file,
            new \DateTimeImmutable('2026-09-01 00:00:00'),
            $publisher,
        );
        $entityManager->persist($file);
        $entityManager->persist($document);
        $entityManager->flush();

        return $version;
    }
}
