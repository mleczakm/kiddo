<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\File;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Entity\WorkshopType;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\LessonModal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class LessonModalConsentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testRequiresWithdrawalAcknowledgementAndRecordsAllBookingEvidence(): void
    {
        Clock::set(new MockClock('2026-09-10 10:00:00'));
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->withPhone('501111111')->assemble();
        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $metadata = LessonMetadataAssembler::new()->withTitle('Warsztat prawny')->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata($metadata)
            ->withSchedule(new \DateTimeImmutable('2026-09-11 10:30:00'))
            ->assemble();
        $lesson->setSeries($series);
        $entityManager->persist($user);
        $entityManager->persist($series);
        $entityManager->persist($lesson);
        $entityManager->flush();
        $this->publishLegalDocuments($entityManager, $user);

        $termsContents = 'individual lesson terms';
        $termsFile = new File(
            'lesson-terms.pdf',
            'application/pdf',
            strlen($termsContents),
            hash('sha256', $termsContents),
            base64_encode($termsContents),
        );
        $workshopTerms = new WorkshopFile($metadata, $termsFile, WorkshopFileRole::TERMS_OF_USE);
        $entityManager->persist($termsFile);
        $entityManager->persist($workshopTerms);
        $entityManager->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $client->loginUser($user);
        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'paymentModal' => true,
                'termsAccepted' => true,
                'closeUrl' => '/warsztaty',
            ],
            client: $client,
        );

        $html = (string) $component->render();
        static::assertStringContainsString('/regulamin', $html);
        static::assertStringContainsString('/polityka-prywatnosci', $html);
        static::assertStringContainsString('lesson-terms.pdf', $html);
        static::assertStringContainsString('Aleja Jana Pawła II 12D, 05-250 Radzymin', $html);
        static::assertStringContainsString('Zamawiam z obowiązkiem zapłaty', $html);

        $component->call('processPayment');
        /** @var LessonModal $modal */
        $modal = $component->component();
        static::assertSame('error', $modal->paymentStatus);

        $component->set('withdrawalAcknowledged', true);
        $component->call('processPayment');
        /** @var LessonModal $modal */
        $modal = $component->component();
        static::assertSame('awaiting_payment', $modal->paymentStatus);

        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $registry->resetManager();
        /** @var EntityManagerInterface $freshEntityManager */
        $freshEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var User $freshUser */
        $freshUser = $freshEntityManager->find(User::class, $userId);
        /** @var Booking $booking */
        $booking = $freshEntityManager->getRepository(Booking::class)->findOneBy(['user' => $freshUser]);
        /** @var UserConsentRepository $consentRepository */
        $consentRepository = static::getContainer()->get(UserConsentRepository::class);
        $consents = $consentRepository->findHistoryForUser($freshUser);

        static::assertCount(4, $consents);
        static::assertEqualsCanonicalizing(
            [
                ConsentType::APP_TERMS->value => 1,
                ConsentType::CLASSES_TERMS->value => 2,
                ConsentType::WITHDRAWAL_INFO_ACK->value => 1,
            ],
            array_count_values(array_map(static fn($consent): string => $consent->getType()->value, $consents)),
        );
        static::assertContains(
            sprintf('workshop_file:%s', $workshopTerms->getId()),
            array_map(static fn($consent): ?string => $consent->getDocumentRef(), $consents),
        );
        foreach ($consents as $consent) {
            static::assertSame(ConsentSource::BOOKING_MODAL, $consent->getSource());
            static::assertSame(sprintf('booking:%s', $booking->getId()), $consent->getContext());
        }
    }

    private function publishLegalDocuments(EntityManagerInterface $entityManager, User $publisher): void
    {
        foreach (LegalDocumentType::cases() as $type) {
            $contents = sprintf('%s booking document', $type->value);
            $file = new File(
                sprintf('%s.pdf', $type->value),
                'application/pdf',
                strlen($contents),
                hash('sha256', $contents),
                base64_encode($contents),
            );
            $document = new LegalDocument($type);
            new LegalDocumentVersion($document, $file, Clock::get()->now()->modify('-1 day'), $publisher);
            $entityManager->persist($file);
            $entityManager->persist($document);
        }

        $entityManager->flush();
    }
}
