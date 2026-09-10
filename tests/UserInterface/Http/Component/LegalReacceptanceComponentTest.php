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

    public function testBannerAppearsForAStaleAcceptanceAndReacceptRecordsTheCurrentVersion(): void
    {
        $client = static::createClient();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        $user = UserAssembler::new()->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $publisher = new User('reaccept-publisher@example.test', 'Publisher');
        $em->persist($publisher);

        // v1 effective, user accepts it.
        $this->persistVersion($em, $publisher, new \DateTimeImmutable('-30 days'));
        $bus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                LegalDocumentType::APP_TERMS,
                'Akceptuję.',
            )),
        ]));

        // v2 becomes current; the user's acceptance is now stale.
        $v2 = $this->persistVersion($em, $publisher, new \DateTimeImmutable('-1 day'));

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: LegalReacceptanceComponent::class, client: $client);
        static::assertStringContainsString('Zaktualizowaliśmy nasze dokumenty', $component->render()->toString());

        $component->call('reaccept');

        /** @var UserConsentRepository $consents */
        $consents = $container->get(UserConsentRepository::class);
        $latest = $consents->findLatestActive($user, ConsentType::APP_TERMS, LegalDocumentType::APP_TERMS);
        static::assertNotNull($latest);
        static::assertSame(ConsentSource::TERMS_REACCEPT, $latest->getSource());
        static::assertTrue($latest->getDocumentVersion()?->getId()->equals($v2->getId()));

        static::assertStringNotContainsString(
            'Zaktualizowaliśmy nasze dokumenty',
            $this->createLiveComponent(name: LegalReacceptanceComponent::class, client: $client)->render()->toString(),
        );
    }

    private function persistVersion(
        EntityManagerInterface $em,
        User $publisher,
        \DateTimeImmutable $effectiveFrom,
    ): LegalDocumentVersion {
        $contents = 'app terms ' . $effectiveFrom->format('U');
        $file = new File(
            'app_terms.pdf',
            'application/pdf',
            \strlen($contents),
            hash('sha256', $contents),
            base64_encode($contents),
        );
        $document = $em->getRepository(LegalDocument::class)->findOneBy([
            'type' => LegalDocumentType::APP_TERMS,
        ]) ?? new LegalDocument(LegalDocumentType::APP_TERMS);
        $version = new LegalDocumentVersion($document, $file, $effectiveFrom, $publisher);
        $em->persist($file);
        $em->persist($document);
        $em->persist($version);
        $em->flush();

        return $version;
    }
}
