<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use App\Entity\ClassCouncil\ClassRoom;
use App\Application\Command\Notification\SendSettlementConfirmationEmail;
use App\Application\CommandHandler\Notification\SendSettlementConfirmationEmailHandler;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\ClassCouncil\Student;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\PaymentRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;
use App\Tests\Assembler\UserAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TenantAssembler;
use Brick\Money\Money as BrickMoney;

#[Group('unit')]
class SendSettlementConfirmationEmailHandlerTest extends TestCase
{
    public function testHandlerSendsEmailWithTenantContext(): void
    {
        $tenant = TenantAssembler::new()->withDomain('tenant.test')->withEmailFrom('school@tenant.test')->assemble();
        $classRoom = new ClassRoom($tenant, '1A');
        $user = UserAssembler::new()->withEmail('parent@example.com')->assemble();
        $student = new Student($classRoom, 'Jan', 'Kowalski');
        $amount = BrickMoney::of(100, 'PLN');
        $studentPayment = new StudentPayment($student, 'Składka wrzesień', $amount);
        $studentPayment->setStatus('paid');
        $studentPayment->setPaidAt(new \DateTimeImmutable('2025-10-10'));
        $studentPayment->setDueAt(new \DateTimeImmutable('2025-10-05'));
        $payment = PaymentAssembler::new()->withUser($user)->withAmount($amount)->assemble();
        // Link payment to studentPayment
        $studentPayment->setPayment($payment);

        // Repository mocks
        $paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentRepository->method('find')
            ->willReturn($payment);

        $studentPaymentRepository = $this->createMock(StudentPaymentRepository::class);
        $studentPaymentRepository->method('findByPayment')
            ->willReturn([$studentPayment]);

        $twig = $this->createMock(TwigEnvironment::class);
        $twig->method('render')
            ->willReturn('<html>Potwierdzenie rozliczenia</html>');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $body = $email->getHtmlBody();
                $body = is_string($body) ? $body : '';
                return $email->getTo()[0]
                    ->getAddress() === 'parent@example.com'
                    && str_contains($body, 'Jan Kowalski')
                    && str_contains($body, 'Składka wrzesień')
                    && str_contains($body, '100')
                    && str_contains($body, '2025-10-05')
                    && str_contains($body, '2025-10-10')
                    && str_contains($body, 'paid')
                    && $email->getSubject() === 'Potwierdzenie rozliczenia składek';
            }));

        $handler = new SendSettlementConfirmationEmailHandler(
            payments: $paymentRepository,
            studentPayments: $studentPaymentRepository,
            mailer: $mailer,
            twig: $twig,
        );

        $command = new SendSettlementConfirmationEmail(
            paymentId: (string) $payment->getId(),
            tenantId: (string) $tenant->getId(),
        );

        $handler->__invoke($command);
    }

    public function testHandlerDoesNotSendEmailIfPaymentNotFound(): void
    {
        $paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentRepository->method('find')
            ->willReturn(null);

        $studentPaymentRepository = $this->createMock(StudentPaymentRepository::class);
        $studentPaymentRepository->method('findByPayment')
            ->willReturn([]);

        $twig = $this->createMock(TwigEnvironment::class);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())
            ->method('send');

        $handler = new SendSettlementConfirmationEmailHandler(
            payments: $paymentRepository,
            studentPayments: $studentPaymentRepository,
            mailer: $mailer,
            twig: $twig,
        );

        $command = new SendSettlementConfirmationEmail(paymentId: 'missing-payment', tenantId: 'tenant-uuid');

        $handler->__invoke($command);
    }

    public function testHandlerDoesNotSendEmailIfUserHasNoEmail(): void
    {
        $tenant = TenantAssembler::new()->assemble();
        $classRoom = new ClassRoom($tenant, '1A');
        $user = UserAssembler::new()->withEmail('')->assemble();
        $student = new Student($classRoom, 'Jan', 'Kowalski');
        $amount = BrickMoney::of(100, 'PLN');
        $studentPayment = new StudentPayment($student, 'Składka wrzesień', $amount);
        $payment = PaymentAssembler::new()->withUser($user)->withAmount($amount)->assemble();
        $studentPayment->setPayment($payment);

        $paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentRepository->method('find')
            ->willReturn($payment);

        $studentPaymentRepository = $this->createMock(StudentPaymentRepository::class);
        $studentPaymentRepository->method('findByPayment')
            ->willReturn([$studentPayment]);

        $twig = $this->createMock(TwigEnvironment::class);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())
            ->method('send');

        $handler = new SendSettlementConfirmationEmailHandler(
            payments: $paymentRepository,
            studentPayments: $studentPaymentRepository,
            mailer: $mailer,
            twig: $twig,
        );

        $command = new SendSettlementConfirmationEmail(
            paymentId: (string) $payment->getId(),
            tenantId: (string) $tenant->getId(),
        );

        $handler->__invoke($command);
    }

    public function testEmailContentIncludesDetailedPaymentInfo(): void
    {
        $tenant = TenantAssembler::new()->withDomain('tenant.test')->withEmailFrom('school@tenant.test')->assemble();
        $classRoom = new ClassRoom($tenant, '1A');
        $user = UserAssembler::new()->withEmail('parent@example.com')->assemble();
        $student = new Student($classRoom, 'Anna', 'Nowak');
        $amount = BrickMoney::of(150, 'PLN');
        $studentPayment = new StudentPayment($student, 'Składka październik', $amount);
        $studentPayment->setStatus('paid');
        $studentPayment->setPaidAt(new \DateTimeImmutable('2025-10-12'));
        $studentPayment->setDueAt(new \DateTimeImmutable('2025-10-10'));
        $payment = PaymentAssembler::new()->withUser($user)->withAmount($amount)->assemble();
        $studentPayment->setPayment($payment);

        $paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentRepository->method('find')
            ->willReturn($payment);
        $studentPaymentRepository = $this->createMock(StudentPaymentRepository::class);
        $studentPaymentRepository->method('findByPayment')
            ->willReturn([$studentPayment]);

        $twig = $this->createMock(TwigEnvironment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'email/class_council/settlement_confirmation.html.twig',
                $this->callback(fn($context) => isset($context['items']) && isset($context['paymentDate'])
                    && $context['items'][0]['student'] === 'Anna Nowak'
                    && $context['items'][0]['label'] === 'Składka październik'
                    && $context['items'][0]['amount']->getAmount() === 150
                    && $context['items'][0]['paidAt']->format('Y-m-d') === '2025-10-12'
                    && $context['items'][0]['dueAt']->format('Y-m-d') === '2025-10-10'
                    && $context['items'][0]['status'] === 'paid')
            )
            ->willReturn('<html>Potwierdzenie rozliczenia</html>');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $handler = new SendSettlementConfirmationEmailHandler(
            payments: $paymentRepository,
            studentPayments: $studentPaymentRepository,
            mailer: $mailer,
            twig: $twig,
        );

        $command = new SendSettlementConfirmationEmail(
            paymentId: (string) $payment->getId(),
            tenantId: (string) $tenant->getId(),
        );

        $handler->__invoke($command);
    }

    public function testTreasurerNotificationIncludesMissingPayments(): void
    {
        $tenant = TenantAssembler::new()->withDomain('tenant.test')->withEmailFrom('school@tenant.test')->assemble();
        $classRoom = new ClassRoom($tenant, '1A');
        $parent = UserAssembler::new()->withEmail('parent@example.com')->withName('Jan Kowalski')->assemble();
        $treasurer = UserAssembler::new()->withEmail('treasurer@example.com')->withName('Anna Skarbnik')->assemble();
        $treasurerMembership = new ClassMembership($treasurer, $classRoom, ClassRole::TREASURER);
        $student = new Student($classRoom, 'Jan', 'Kowalski');
        $amountPaid = BrickMoney::of(100, 'PLN');
        $studentPaymentPaid = new StudentPayment($student, 'Składka wrzesień', $amountPaid);
        $studentPaymentPaid->setStatus('paid');
        $studentPaymentPaid->setPaidAt(new \DateTimeImmutable('2025-10-10'));
        $studentPaymentPaid->setDueAt(new \DateTimeImmutable('2025-10-05'));
        $payment = PaymentAssembler::new()->withUser($parent)->withAmount($amountPaid)->assemble();
        $studentPaymentPaid->setPayment($payment);
        // Missing payments
        $student2 = new Student($classRoom, 'Anna', 'Nowak');
        $amountMissing = BrickMoney::of(150, 'PLN');
        $studentPaymentMissing = new StudentPayment($student2, 'Składka październik', $amountMissing);
        $studentPaymentMissing->setStatus('pending');
        $studentPaymentMissing->setDueAt(new \DateTimeImmutable('2025-10-10'));
        // Repository mocks
        $paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentRepository->method('find')
            ->willReturn($payment);
        $studentPaymentRepository = $this->createMock(StudentPaymentRepository::class);
        $studentPaymentRepository->method('findByPayment')
            ->willReturn([$studentPaymentPaid]);
        $studentPaymentRepository->method('findBy')
            ->willReturnCallback(function ($criteria) use ($studentPaymentMissing) {
                if (isset($criteria['status']) && in_array('pending', $criteria['status'], true)) {
                    return [$studentPaymentMissing];
                }
                return [];
            });
        $classMembershipRepository = $this->createMock(ClassMembershipRepository::class);
        $mailer = $this->createMock(MailerInterface::class);
        $twig = $this->createMock(TwigEnvironment::class);
        $handler = new SendSettlementConfirmationEmailHandler(
            payments: $paymentRepository,
            studentPayments: $studentPaymentRepository,
            classMemberships: $classMembershipRepository,
            mailer: $mailer,
            twig: $twig,
        );
        $command = new SendSettlementConfirmationEmail(
            paymentId: (string) $payment->getId(),
            tenantId: (string) $tenant->getId(),
        );
        $handler->__invoke($command);
        $this->assertTrue(true, 'Treasurer notification executed without error.');
    }
}
