<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use PHPUnit\Framework\Attributes\Group;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use App\Tests\Assembler\PaymentAssembler;
use App\Entity\Payment;
use App\Entity\Tenant;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Application\CommandHandler\Notification\SendSettlementConfirmationEmailHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Application\Command\Notification\SendSettlementConfirmationEmail;
use App\Repository\PaymentRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment as TwigEnvironment;
use Doctrine\ORM\EntityManagerInterface;

#[Group('functional')]
class SettlementConfirmationEmailFunctionalTest extends WebTestCase
{
    public function testSettlementConfirmationEmailIsSent(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        $paymentRepository = $container->get(PaymentRepository::class);
        $studentPaymentRepository = $container->get(StudentPaymentRepository::class);
        $classMembershipRepository = $container->get(ClassMembershipRepository::class);
        $mailer = $container->get(MailerInterface::class);
        $twig = $container->get(TwigEnvironment::class);
        $em = $container->get(EntityManagerInterface::class);

        // Create and persist User using UserAssembler
        $user = UserAssembler::new()
            ->withEmail('parent@example.com')
            ->withName('Jan Kowalski')
            ->withCreatedAt(new \DateTimeImmutable())
            ->assemble();
        $em->persist($user);

        // Create and persist Payment using PaymentAssembler
        $amount = Money::of(100, 'PLN');
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount($amount)
            ->withStatus(Payment::STATUS_PAID)
            ->withCreatedAt(new \DateTimeImmutable())
            ->assemble();
        $em->persist($payment);



        // Create and persist Student, ClassRoom, StudentPayment
        $tenant = $em->getRepository(Tenant::class)->findOneBy([]) ?? new Tenant('TestTenant', 'tenant.test');
        $em->persist($tenant);
        $classRoom = new ClassRoom($tenant, '1A');
        $em->persist($classRoom);
        $student = new Student($classRoom, 'Jan', 'Kowalski');
        $em->persist($student);
        $studentPayment = new StudentPayment($student, 'Składka wrzesień', $amount);
        $studentPayment->setStatus(StudentPayment::STATUS_PAID);
        $studentPayment->setPaidAt(new \DateTimeImmutable());
        $studentPayment->setPayment($payment);
        $em->persist($studentPayment);

        $classMembership = new ClassMembership($user, $classRoom, ClassRole::TREASURER);
        $em->persist($classMembership);

        $em->flush();

        $handler = $container->get(SendSettlementConfirmationEmailHandler::class);
        $command = new SendSettlementConfirmationEmail(
            paymentId: (string) $payment->getId(),
            tenantId: (string) $tenant->getId()
        );

        $handler->__invoke($command);

        $this->assertQueuedEmailCount(1);
    }
}
