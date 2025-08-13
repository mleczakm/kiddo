<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\SendReservationNotification;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\PaymentCodeAssembler;
use App\Tests\Assembler\UserAssembler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class SendReservationNotificationHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;
    use InteractsWithMessenger;

    public function testInvoke(): void
    {
        self::bootKernel();

        $user = UserAssembler::new()->assemble();
        BookingAssembler::new()
            ->withPayment(
                $payment = PaymentAssembler::new()
                    ->withPaymentCode($paymentCode = PaymentCodeAssembler::new() ->withCode('TEST123') ->assemble())
                    ->assemble()
            )
            ->withUser($user)
            ->assemble();
        $paymentAmount = $payment->getAmount();
        $email = $user->getEmail();

        $command = new SendReservationNotification(
            $email,
            $user->getEmailString(),
            $paymentCode->getCode(),
            $paymentAmount
        );

        $this->bus()
            ->dispatch($command);

        $this->mailer()
            ->assertSentEmailCount(1);

        $firstEmail = $this->mailer()
            ->sentEmails()
            ->first();
        $this->assertSame($firstEmail->getTo()[0]->getAddress(), $email);
        $this->assertSame(
            'Twoja rezerwacja w Warsztatowni Sensorycznej – oczekujemy na płatność',
            $firstEmail->getSubject()
        );
        $this->assertStringContainsString(
            'TEST123',
            (string) ($firstEmail->getHtmlBody() ?? $firstEmail->getTextBody())
        );
        $this->assertStringContainsString(
            $paymentAmount->getAmount()
                ->getIntegralPart() . ' zł',
            (string) ($firstEmail->getHtmlBody() ?? $firstEmail->getTextBody())
        );
        $this->assertStringContainsString(
            '571 531 213',
            (string) ($firstEmail->getHtmlBody() ?? $firstEmail->getTextBody())
        );
        $this->assertStringContainsString(
            '46 2490 0005 0000 4000 1897 5420',
            (string) ($firstEmail->getHtmlBody() ?? $firstEmail->getTextBody())
        );
    }
}
