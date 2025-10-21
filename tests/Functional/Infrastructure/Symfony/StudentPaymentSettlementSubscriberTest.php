<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Symfony;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Payment;
use App\Entity\Tenant;
use App\Entity\Transfer;
use App\Entity\User;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;
use Symfony\Component\Workflow\WorkflowInterface;

#[Group('functional')]
final class StudentPaymentSettlementSubscriberTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    public function testEmailIsSentWhenPaymentIsMarkedPaid(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Arrange: minimal data graph
        $tenant = new Tenant('Test School', null);
        $user = new User('parent@example.com', 'Parent Name');
        $tenant->addUser($user);
        $class = new ClassRoom($tenant, '1A');
        $student = new Student($class, 'Jan', 'Kowalski');
        $student->addParent($user);

        $amount = Money::of(100, 'PLN');
        $payment = new Payment($user, $amount);

        // Add a transfer that makes payment considered paid (satisfies workflow guard)
        $transfer = new Transfer(
            '12 3456 7890 1234 5678 9012 3456',
            'Jan Kowalski',
            'WPŁATA',
            '100,00',
            new \DateTimeImmutable()
        );
        $transfer->setPayment($payment);

        $sp = new StudentPayment($student, 'Rada rodziców', $amount);
        $sp->setPayment($payment);

        $em->persist($tenant);
        $em->persist($user);
        $em->persist($class);
        $em->persist($student);
        $em->persist($payment);
        $em->persist($transfer);
        $em->persist($sp);
        $em->flush();

        // Act: apply workflow transition to paid, which should dispatch entered.paid event
        /** @var WorkflowInterface $workflow */
        $workflow = $container->get('state_machine.payment');
        self::assertTrue($workflow->can($payment, 'pay'), 'Payment should be payable by workflow');
        $workflow->apply($payment, 'pay');
        $em->flush();

        // Assert: an email has been queued (async via Messenger)
        $this->assertQueuedEmailCount(1);
        $event = $this->getMailerEvent();
        self::assertNotNull($event, 'Expected a queued email event');
        $this->assertEmailIsQueued($event);
        /** @var Email $email */
        $email = $event->getMessage();
        $this->assertEmailHeaderSame($email, 'to', 'parent@example.com');
        $this->assertEmailHeaderSame($email, 'subject', 'Potwierdzenie rozliczenia składek');

        $html = $email->getHtmlBody();
        $html ??= $email->getTextBody();
        self::assertNotNull($html, 'Email should contain a body');
        $body = (string) $html;

        // Contains key parts from template
        self::assertStringContainsString('Potwierdzenie płatności', $body);
        self::assertStringContainsString('Jan Kowalski', $body);
        self::assertStringContainsString('Rada rodziców', $body);
        self::assertStringContainsString('100 zł', $body); // money filter default formatting
        self::assertStringContainsString('1/1', $body); // N/M progress
    }
}
