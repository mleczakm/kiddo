<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Application\Command\RecordConsents;
use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\File;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\LegalReacceptanceComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class LegalReacceptanceComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private const string BANNER = 'Potwierdź akceptację dokumentów';

    public function testNoBannerWhenNothingIsPublished(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistUser());

        $rendered = $this
            ->createLiveComponent(name: LegalReacceptanceComponent::class, client: $client)
            ->render()
            ->toString();

        static::assertStringNotContainsString(self::BANNER, $rendered);
    }

    public function testBannerAppearsForAStaleAcceptanceAndReacceptRecordsTheCurrentVersion(): void
    {
        $client = static::createClient();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        $user = $this->persistUser();
        $publisher = $this->persistUser('reaccept-publisher@example.test');

        $this->persistVersion($em, $publisher, new \DateTimeImmutable('-30 days'));
        $this->persistVersion($em, $publisher, new \DateTimeImmutable('-30 days'), LegalDocumentType::PRIVACY);
        $bus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                LegalDocumentType::APP_TERMS,
                'Akceptuję.',
            )),
            new ConsentGrant(ConsentType::PRIVACY, ConsentEvidence::currentDocument(
                LegalDocumentType::PRIVACY,
                'Akceptuję.',
            )),
        ]));

        // A newer Terms version supersedes the user's acceptance; Privacy is unchanged.
        $termsV2 = $this->persistVersion($em, $publisher, new \DateTimeImmutable('-1 day'));

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: LegalReacceptanceComponent::class, client: $client);
        $rendered = $component->render()->toString();
        static::assertStringContainsString(self::BANNER, $rendered);
        static::assertStringContainsString('Regulamin aplikacji', $rendered);
        static::assertStringNotContainsString('Polityka prywatności', $rendered);

        $component->call('reaccept');

        /** @var UserConsentRepository $consents */
        $consents = $container->get(UserConsentRepository::class);
        $latest = $consents->findLatestActive($user, ConsentType::APP_TERMS, LegalDocumentType::APP_TERMS);
        static::assertNotNull($latest);
        static::assertSame(ConsentSource::TERMS_REACCEPT, $latest->getSource());
        static::assertTrue($latest->getDocumentVersion()?->getId()->equals($termsV2->getId()));

        static::assertStringNotContainsString(
            self::BANNER,
            $this->createLiveComponent(name: LegalReacceptanceComponent::class, client: $client)->render()->toString(),
        );
    }

    public function testBannerAlsoCatchesAUserWhoNeverAccepted(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $publisher = $this->persistUser('never-accepted-publisher@example.test');
        $this->persistVersion($em, $publisher, new \DateTimeImmutable('-1 day'));

        $client->loginUser($this->persistUser());
        $rendered = $this
            ->createLiveComponent(name: LegalReacceptanceComponent::class, client: $client)
            ->render()
            ->toString();

        static::assertStringContainsString(self::BANNER, $rendered);
    }

    private function persistUser(?string $email = null): User
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()
            ->withEmail($email ?? sprintf('reaccept-%s@example.test', bin2hex(random_bytes(4))))
            ->withRoles('ROLE_USER')
            ->assemble();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function persistVersion(
        EntityManagerInterface $em,
        User $publisher,
        \DateTimeImmutable $effectiveFrom,
        LegalDocumentType $type = LegalDocumentType::APP_TERMS,
    ): LegalDocumentVersion {
        $contents = $type->value . ' ' . $effectiveFrom->format('U');
        $file = new File(
            $type->value . '.pdf',
            'application/pdf',
            \strlen($contents),
            hash('sha256', $contents),
            base64_encode($contents),
        );
        $document = $em->getRepository(LegalDocument::class)->findOneBy(['type' => $type]) ?? new LegalDocument($type);
        $version = new LegalDocumentVersion($document, $file, $effectiveFrom, $publisher);
        $em->persist($file);
        $em->persist($document);
        $em->persist($version);
        $em->flush();

        return $version;
    }
}
