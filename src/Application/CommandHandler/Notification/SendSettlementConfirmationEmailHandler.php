<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendSettlementConfirmationEmail;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;

final readonly class SendSettlementConfirmationEmailHandler
{
    public function __construct(
        private PaymentRepository $payments,
        private StudentPaymentRepository $studentPayments,
        private ClassMembershipRepository $classMemberships,
        private MailerInterface $mailer,
        private TwigEnvironment $twig,
    ) {}

    public function __invoke(SendSettlementConfirmationEmail $command): void
    {
        $payment = $this->payments->find($command->paymentId);
        if (! $payment instanceof Payment) {
            return;
        }

        $user = $payment->getUser();
        $userEmailAddress = $user->getEmail();
        if ($userEmailAddress === '') {
            return;
        }

        $studentPayments = $this->studentPayments->findByPayment($payment);
        $studentPaymentList = [];
        foreach ($studentPayments as $studentPayment) {
            $student = $studentPayment->getStudent();
            $studentPaymentList[] = [
                'student' => $student->getFirstName() . ' ' . $student->getLastName(),
                'label' => $studentPayment->getLabel(),
                'amount' => $studentPayment->getAmount(),
                'paidAt' => $studentPayment->getPaidAt() ?: $payment->getPaidAt(),
                'dueAt' => $studentPayment->getDueAt(),
                'status' => $studentPayment->getStatus(),
            ];
        }

        // Treasurer notification
        $firstStudentPayment = $studentPayments[0] ?? null;
        if (! $firstStudentPayment instanceof StudentPayment) {
            return;
        }

        $classRoom = $firstStudentPayment->getClassRoom();

        $treasurerMembership = $this->classMemberships->findOneBy([
            'classRoom' => $classRoom,
            'role' => ClassRole::TREASURER,
        ]);

        if ($treasurerMembership instanceof ClassMembership) {
            $treasurer = $treasurerMembership->getUser();
            $treasurerEmail = $treasurer->getEmail();
            if ($treasurerEmail !== '') {
                $missingPayments = $this->studentPayments->findBy([
                    'classRoom' => $classRoom,
                    'status' => ['pending', 'partial'],
                ]);

                $missingAmount = array_reduce(
                    $missingPayments,
                    fn($sum, $sp) => $sum + $sp->getAmount()
                        ->getAmount()
                        ->toFloat(),
                    0.0
                );

                $treasurerHtmlBody = $this->twig->render(
                    'email/class_council/treasurer_payment_notification.html.twig',
                    [
                        'classRoomName' => $classRoom->getName(),
                        'parentName' => $user->getName(),
                        'parentEmail' => $userEmailAddress,
                        'items' => $studentPaymentList,
                        'missingAmount' => $missingAmount,
                    ]
                );

                $treasurerEmailMessage = new Email()
                    ->to($treasurerEmail)
                    ->subject('Nowa wpłata w klasie - podsumowanie zaległości')
                    ->html($treasurerHtmlBody);

                $this->mailer->send($treasurerEmailMessage);
            }
        }
    }
}
