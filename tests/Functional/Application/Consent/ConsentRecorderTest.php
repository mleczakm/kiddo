<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Consent;

use App\Application\Command\RecordConsents;
use App\Application\Command\RevokeConsent;
use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Application\Consent\ConsentStatusReader;
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
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Exercises the consent write path through the command bus - the only way it is
 * reached in production. The bus wraps each handler in a Doctrine transaction,
 * so a dispatch that returns has committed its acceptance set.
 */
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
        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get(MessageBusInterface::class);
        /** @var ConsentStatusReader $statusReader */
        $statusReader = $container->get(ConsentStatusReader::class);
        /** @var UserConsentRepository $repository */
        $repository = $container->get(UserConsentRepository::class);

        $commandBus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::MARKETING_EMAIL, ConsentEvidence::statement(
                'Chcę otrzymywać wiadomości marketingowe.',
                'register',
            )),
        ]));

        $consent = $repository->findLatestActive($user, ConsentType::MARKETING_EMAIL);
        static::assertNotNull($consent);
        static::assertSame('198.51.100.25', $consent->getIp());
        static::assertSame('Functional Test Browser', $consent->getUserAgent());
        static::assertTrue($statusReader->hasCurrent($user, ConsentType::MARKETING_EMAIL));

        $commandBus->dispatch(new RevokeConsent($user, ConsentType::MARKETING_EMAIL, ConsentSource::PROFILE));

        static::assertFalse($statusReader->hasCurrent($user, ConsentType::MARKETING_EMAIL));
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
        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get(MessageBusInterface::class);
        /** @var ConsentStatusReader $statusReader */
        $statusReader = $container->get(ConsentStatusReader::class);
        /** @var UserConsentRepository $repository */
        $repository = $container->get(UserConsentRepository::class);

        static::assertEqualsCanonicalizing(
            [LegalDocumentType::APP_TERMS, LegalDocumentType::PRIVACY],
            $statusReader->outdatedDocuments($user),
        );

        $commandBus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                LegalDocumentType::APP_TERMS,
                'Akceptuję regulamin aplikacji.',
            )),
        ]));

        $consent = $repository->findLatestActive($user, ConsentType::APP_TERMS, LegalDocumentType::APP_TERMS);
        static::assertNotNull($consent);
        static::assertTrue($consent->getDocumentVersion()?->getId()->equals($version->getId()));
        static::assertTrue($statusReader->hasCurrent($user, ConsentType::APP_TERMS));
        static::assertSame([LegalDocumentType::PRIVACY], $statusReader->outdatedDocuments($user));
    }

    public function testMissingCurrentDocumentDoesNotCreateConsent(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $user = $this->persistUser($entityManager);
        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get(MessageBusInterface::class);

        $commandBus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::PRIVACY, ConsentEvidence::currentDocument(
                LegalDocumentType::PRIVACY,
                'Akceptuję politykę prywatności.',
            )),
        ]));

        /** @var UserConsentRepository $repository */
        $repository = $container->get(UserConsentRepository::class);
        static::assertSame([], $repository->findHistoryForUser($user));
    }

    public function testRecordsACompleteConsentSetInOneTransaction(): void
    {
        Clock::set(new MockClock('2026-09-10 12:00:00'));
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $user = $this->persistUser($entityManager);
        $this->persistLegalVersion($entityManager, $user, LegalDocumentType::APP_TERMS);
        $this->persistLegalVersion($entityManager, $user, LegalDocumentType::PRIVACY);
        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get(MessageBusInterface::class);

        $commandBus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                LegalDocumentType::APP_TERMS,
                'Akceptuję dokumenty.',
            )),
            new ConsentGrant(ConsentType::PRIVACY, ConsentEvidence::currentDocument(
                LegalDocumentType::PRIVACY,
                'Akceptuję dokumenty.',
            )),
        ]));

        /** @var UserConsentRepository $repository */
        $repository = $container->get(UserConsentRepository::class);
        $consents = $repository->findHistoryForUser($user);
        static::assertCount(2, $consents);
        static::assertEqualsCanonicalizing(
            [ConsentType::APP_TERMS, ConsentType::PRIVACY],
            array_map(static fn($consent): ConsentType => $consent->getType(), $consents),
        );
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
        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get(MessageBusInterface::class);

        $commandBus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                LegalDocumentType::APP_TERMS,
                'Akceptuję dokumenty.',
            )),
            new ConsentGrant(ConsentType::PRIVACY, ConsentEvidence::currentDocument(
                LegalDocumentType::PRIVACY,
                'Akceptuję dokumenty.',
            )),
        ]));

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
