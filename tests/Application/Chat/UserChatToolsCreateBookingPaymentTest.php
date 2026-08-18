<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Entity\TicketType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SettingAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class UserChatToolsCreateBookingPaymentTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testCreateBookingReturnsBlikPaymentInstructions(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $paymentSettings = SettingAssembler::new()->asPayment()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->persist($paymentSettings);
        $em->flush();

        /** @var ChatToolRegistry $registry */
        $registry = self::getContainer()->get(ChatToolRegistry::class);
        $actor = new ChatActor($user, ['ROLE_USER']);

        $result = $registry->call('user.create_booking', $actor, [
            'confirm' => true,
            'lesson_id' => (string) $lesson->getId(),
            'ticket_type' => TicketType::ONE_TIME->value,
        ]);

        static::assertTrue($result->ok, $result->error ?? $result->summary);
        $paymentCode = $result->data['payment_code'] ?? null;
        $bookingId = $result->data['booking_id'] ?? null;
        static::assertIsString($paymentCode);
        static::assertNotSame('', $paymentCode);
        static::assertIsString($bookingId);
        static::assertNotSame('', $bookingId);

        $payment = $result->data['payment'] ?? [];
        static::assertIsArray($payment);
        static::assertSame($paymentCode, $payment['code'] ?? null);
        static::assertSame('blik_phone_transfer', $payment['method'] ?? null);
        static::assertArrayHasKey('blik_phone', $payment);
        static::assertIsString($payment['blik_phone']);
        static::assertArrayHasKey('bank_account', $payment);
        static::assertSame(24, $payment['valid_hours'] ?? null);
        static::assertStringContainsString($paymentCode, $result->summary);
        static::assertStringContainsString('BLIK', $result->summary);
        static::assertStringContainsString($payment['blik_phone'], $result->summary);

        $byCode = $registry->call('user.get_payment_instructions', $actor, [
            'payment_code' => $paymentCode,
        ]);
        static::assertTrue($byCode->ok, $byCode->error ?? $byCode->summary);
        static::assertSame($paymentCode, $byCode->data['code'] ?? null);
        static::assertStringContainsString('BLIK', $byCode->summary);

        $byBooking = $registry->call('user.get_payment_instructions', $actor, [
            'booking_id' => $bookingId,
        ]);
        static::assertTrue($byBooking->ok, $byBooking->error ?? $byBooking->summary);
        static::assertSame($paymentCode, $byBooking->data['code'] ?? null);

        $this->mailer()->assertSentEmailCount(1);
        $email = $this->mailer()->sentEmails()->first();
        static::assertStringContainsString($paymentCode, (string) ($email->getHtmlBody() ?? $email->getTextBody()));
    }

    public function testGuestCannotCreateBooking(): void
    {
        self::bootKernel();

        /** @var ChatToolRegistry $registry */
        $registry = self::getContainer()->get(ChatToolRegistry::class);
        $result = $registry->call('user.create_booking', ChatActor::guest(), [
            'confirm' => true,
            'lesson_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'ticket_type' => TicketType::ONE_TIME->value,
        ]);

        static::assertFalse($result->ok);
        static::assertStringContainsString('zalogować', $result->summary);
    }
}
