<?php

declare(strict_types=1);

namespace App\Tests\Application\Account;

use App\Application\Account\UserAnonymizer;
use App\Application\Command\RecordConsents;
use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Entity\Child;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\Notification;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\ChildRepository;
use App\Infrastructure\Doctrine\Repository\NotificationRepository;
use App\Infrastructure\Doctrine\Repository\PaymentRepository;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\PaymentAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('functional')]
final class UserAnonymizerTest extends KernelTestCase
{
    public function testScrubsPersonalDataButKeepsFinancialRecords(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = new User('victim@example.test', 'Jan Kowalski');
        $em->persist($user);
        $em->flush();

        $child = new Child($user, 'Zosia Kowalska', new \DateTimeImmutable('2021-05-05'));
        $em->persist($child);

        $payment = PaymentAssembler::new()->withUser($user)->assemble();
        $em->persist($payment);
        $booking = BookingAssembler::new()->withUser($user)->withNotes('prosze o salę na parterze')->assemble();
        $em->persist($booking);

        $em->persist(new Notification($user, 'Cześć Jan Kowalski', 'treść'));
        $em->persist(new ActivityLog(ActivityType::NEWSLETTER_SUBSCRIBED, 'Jan Kowalski zapisał się', $user));
        $em->flush();

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);
        $bus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::MARKETING_EMAIL, ConsentEvidence::statement('Zgoda.')),
        ]));

        $paymentId = $payment->getId();

        /** @var UserAnonymizer $anonymizer */
        $anonymizer = $container->get(UserAnonymizer::class);
        $anonymizer->anonymize($user);
        $em->clear();

        /** @var UserRepository $users */
        $users = $container->get(UserRepository::class);
        $reloaded = $users->find($user->getId());
        static::assertNotNull($reloaded);
        static::assertTrue($reloaded->getLifecycle()->isAnonymized());
        static::assertSame('Użytkownik usunięty', $reloaded->getName());
        static::assertStringNotContainsString('victim', $reloaded->getEmail());
        static::assertSame([], $reloaded->getRoles());

        /** @var ChildRepository $children */
        $children = $container->get(ChildRepository::class);
        $childRow = $children->findByOwner($reloaded)[0];
        static::assertSame('Uczestnik', $childRow->getName());
        static::assertNull($childRow->getBirthday());

        /** @var BookingRepository $bookings */
        $bookings = $container->get(BookingRepository::class);
        $bookingRow = $bookings->find($booking->getId());
        static::assertNotNull($bookingRow);
        static::assertNull($bookingRow->getNotes());

        /** @var NotificationRepository $notifications */
        $notifications = $container->get(NotificationRepository::class);
        static::assertSame([], $notifications->findBy(['user' => $reloaded]));

        /** @var UserConsentRepository $consents */
        $consents = $container->get(UserConsentRepository::class);
        foreach ($consents->findHistoryForUser($reloaded) as $consent) {
            static::assertFalse($consent->isActive());
        }

        // Financial record survives.
        /** @var PaymentRepository $payments */
        $payments = $container->get(PaymentRepository::class);
        static::assertNotNull($payments->find($paymentId));
    }

    public function testIsIdempotent(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $user = new User('twice@example.test', 'Twice');
        $em->persist($user);
        $em->flush();

        /** @var UserAnonymizer $anonymizer */
        $anonymizer = $container->get(UserAnonymizer::class);
        $anonymizer->anonymize($user);
        $stamp = $user->getLifecycle()->anonymizedAt();
        $anonymizer->anonymize($user);

        static::assertEquals($stamp, $user->getLifecycle()->anonymizedAt());
    }
}
