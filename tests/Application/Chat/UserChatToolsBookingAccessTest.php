<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\PaymentCodeAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class UserChatToolsBookingAccessTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testListUpcomingLessonsSeesBeyondTheCurrentWeekByDefault(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $soon = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule(new \DateTimeImmutable('+40 days')->setTime(10, 0))
            ->assemble();
        $em->persist($soon);
        $em->flush();

        /** @var ChatToolRegistry $registry */
        $registry = self::getContainer()->get(ChatToolRegistry::class);

        $all = $registry->call('user.list_upcoming_lessons', ChatActor::guest(), []);
        static::assertTrue($all->ok, $all->error ?? $all->summary);
        static::assertContains((string) $soon->getId(), $this->lessonIds($all->data));

        // Narrowing to *this* week must exclude the 40-day-out lesson.
        $thisWeek = $registry->call('user.list_upcoming_lessons', ChatActor::guest(), [
            'week' => new \DateTimeImmutable('monday this week')->format('Y-m-d'),
        ]);
        static::assertTrue($thisWeek->ok);
        static::assertNotContains((string) $soon->getId(), $this->lessonIds($thisWeek->data));
    }

    public function testPaymentCodeAccessEmailsACodeThenUnlocksTheBooking(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('parent-access@example.com')->assemble();
        $payment = PaymentAssembler::new()->withUser($user)->assemble();
        $code = PaymentCodeAssembler::new()->withCode('AB12')->withPayment($payment)->assemble();
        $em->persist($user);
        $em->persist($payment);
        $em->persist($code);
        $em->flush();

        /** @var ChatToolRegistry $registry */
        $registry = self::getContainer()->get(ChatToolRegistry::class);

        $request = $registry->call('user.request_booking_access', ChatActor::guest(), ['payment_code' => 'ab12']);
        static::assertTrue($request->ok, $request->error ?? $request->summary);
        static::assertArrayNotHasKey('bookings', $request->data);
        static::assertStringContainsString('***', (string) ($request->data['email_hint'] ?? ''));

        $this->mailer()->assertSentEmailCount(1);
        // Match the code by its surrounding phrase so email-theme hex colours (#583818, …)
        // don't get picked up as a "6-digit code".
        $body =
            (string) $this->mailer()->sentEmails()->first()->getTextBody()
            . "\n"
            . (string) $this->mailer()->sentEmails()->first()->getHtmlBody();
        static::assertSame(1, preg_match('/weryfikacyjny to:\s*(?:<[^>]+>\s*)*(\d{6})/i', $body, $m), $body);
        $verificationCode = $m[1];

        $wrong = $registry->call('user.confirm_booking_access', ChatActor::guest(), [
            'payment_code' => 'AB12',
            'code' => '000000',
        ]);
        static::assertFalse($wrong->ok);

        $ok = $registry->call('user.confirm_booking_access', ChatActor::guest(), [
            'payment_code' => 'AB12',
            'code' => $verificationCode,
        ]);
        static::assertTrue($ok->ok, $ok->error ?? $ok->summary);
        static::assertArrayHasKey('bookings', $ok->data);
        static::assertIsString($ok->data['chat_token'] ?? null);
        static::assertNotSame('', $ok->data['chat_token']);
    }

    public function testUnknownPaymentCodeIsRejectedWithoutSendingEmail(): void
    {
        self::bootKernel();

        /** @var ChatToolRegistry $registry */
        $registry = self::getContainer()->get(ChatToolRegistry::class);

        $result = $registry->call('user.request_booking_access', ChatActor::guest(), ['payment_code' => 'ZZZZ']);
        static::assertFalse($result->ok);
        $this->mailer()->assertSentEmailCount(0);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function lessonIds(array $data): array
    {
        $lessons = $data['lessons'] ?? [];
        static::assertIsArray($lessons);
        /** @var list<array{id?: string}> $lessons */

        return array_values(array_map(static fn(array $l): string => (string) ($l['id'] ?? ''), $lessons));
    }
}
