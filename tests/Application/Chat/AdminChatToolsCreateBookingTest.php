<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\ToolResult;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\TicketType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SettingAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class AdminChatToolsCreateBookingTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private ChatToolRegistry $registry;

    private ChatActor $actor;

    private Lesson $lesson;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        $registry = self::getContainer()->get(ChatToolRegistry::class);
        static::assertInstanceOf(ChatToolRegistry::class, $registry);
        $this->registry = $registry;

        $admin = UserAssembler::new()->assemble();
        $this->lesson = LessonAssembler::new()->assemble();
        $paymentSettings = SettingAssembler::new()->asPayment()->assemble();
        $this->em->persist($admin);
        $this->em->persist($this->lesson);
        $this->em->persist($paymentSettings);
        $this->em->flush();

        $this->actor = new ChatActor($admin, ['ROLE_ADMIN']);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function createBooking(string $paymentMode, array $extra = []): ToolResult
    {
        return $this->registry->call('admin.create_booking', $this->actor, [
            'confirm' => true,
            'lesson_id' => (string) $this->lesson->getId(),
            'email' => 'parent@example.com',
            'ticket_type' => TicketType::ONE_TIME->value,
            'payment' => $paymentMode,
            ...$extra,
        ]);
    }

    private function reloadBooking(ToolResult $result): Booking
    {
        $id = (string) ($result->data['id'] ?? '');
        static::assertNotSame('', $id);
        $this->em->clear();
        $booking = $this->em->find(Booking::class, Ulid::fromString($id));
        static::assertInstanceOf(Booking::class, $booking);

        return $booking;
    }

    public function testPaidModeCreatesActivePaidBooking(): void
    {
        $result = $this->createBooking('paid');
        static::assertTrue($result->ok, $result->error ?? $result->summary);

        $booking = $this->reloadBooking($result);
        static::assertSame(Booking::STATUS_ACTIVE, $booking->getStatus());
        $payment = $booking->getPayment();
        static::assertInstanceOf(Payment::class, $payment);
        static::assertSame(Payment::STATUS_PAID, $payment->getStatus());
    }

    public function testOnSiteModeCreatesActiveBookingWithPendingPayOnPlacePayment(): void
    {
        $result = $this->createBooking('on_site');
        static::assertTrue($result->ok, $result->error ?? $result->summary);

        $booking = $this->reloadBooking($result);
        static::assertSame(Booking::STATUS_ACTIVE, $booking->getStatus());
        $payment = $booking->getPayment();
        static::assertInstanceOf(Payment::class, $payment);
        static::assertSame(Payment::STATUS_PENDING, $payment->getStatus());
        static::assertSame(PaymentMethod::PAY_ON_PLACE, $payment->getMethod());
    }

    public function testSendCodeModeCreatesPendingBookingWithPaymentCode(): void
    {
        $result = $this->createBooking('send_code');
        static::assertTrue($result->ok, $result->error ?? $result->summary);
        static::assertStringContainsString('BLIK', $result->summary);

        $booking = $this->reloadBooking($result);
        static::assertSame(Booking::STATUS_PENDING, $booking->getStatus());
        $payment = $booking->getPayment();
        static::assertInstanceOf(Payment::class, $payment);
        $code = $payment->getPaymentCode();
        static::assertNotNull($code);
        static::assertNotSame('', $code->getCode());
    }

    public function testPriceOverrideIsApplied(): void
    {
        $result = $this->createBooking('on_site', ['price_override' => '90.00']);
        static::assertTrue($result->ok, $result->error ?? $result->summary);

        $booking = $this->reloadBooking($result);
        $payment = $booking->getPayment();
        static::assertInstanceOf(Payment::class, $payment);
        static::assertSame('90.00', $payment->getAmount()->getAmount()->__toString());
    }

    public function testRejectsUnknownTicketType(): void
    {
        $result = $this->registry->call('admin.create_booking', $this->actor, [
            'confirm' => true,
            'lesson_id' => (string) $this->lesson->getId(),
            'email' => 'parent@example.com',
            'ticket_type' => TicketType::CARNET_4->value,
            'payment' => 'paid',
        ]);

        static::assertFalse($result->ok);
    }

    public function testRejectsInvalidPriceOverride(): void
    {
        $result = $this->createBooking('on_site', ['price_override' => 'not-a-number']);

        static::assertFalse($result->ok);
        static::assertStringContainsString('price_override', $result->error ?? $result->summary);
    }
}
