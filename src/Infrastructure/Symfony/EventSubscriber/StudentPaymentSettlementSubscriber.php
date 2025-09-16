<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\EventSubscriber;

use App\Entity\Payment;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use Doctrine\ORM\EntityManagerInterface; // not used but keep for clarity
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\WorkflowInterface;
use Twig\Environment as TwigEnvironment;

/**
 * When a Payment enters the "paid" place, settle linked StudentPayments via their workflow
 * and email the payer a settlement confirmation with a list of deposits covered.
 */
#[AsEventListener(event: 'workflow.payment.entered.paid', method: 'onPaymentEnteredPaid')]
final readonly class StudentPaymentSettlementSubscriber
{
    public function __construct(
        private StudentPaymentRepository $studentPayments,
        private EntityManagerInterface $em,
        #[Autowire(service: 'state_machine.student_payment')]
        private WorkflowInterface $studentPaymentStateMachine,
        private MailerInterface $mailer,
        private TwigEnvironment $twig,
    ) {}

    public function onPaymentEnteredPaid(EnteredEvent $event): void
    {
        $subject = $event->getSubject();
        if (! $subject instanceof Payment) {
            return;
        }

        // Find StudentPayment records linked to this payment and settle them
        $items = $this->studentPayments->findByPayment($subject);
        if ($items === []) {
            return;
        }

        foreach ($items as $sp) {
            if ($this->studentPaymentStateMachine->can($sp, 'settle')) {
                $this->studentPaymentStateMachine->apply($sp, 'settle');
            } else {
                // Fallback in case workflow not applicable (already paid): ensure consistency
                if (method_exists($sp, 'markPaid')) {
                    $sp->markPaid();
                }
            }
        }

        $this->em->flush();

        // Send confirmation email to the payer
        try {
            $user = $subject->getUser();
            $to = method_exists($user, 'getEmail') ? $user->getEmail() : null;
            if ($to) {
                $list = [];
                foreach ($items as $sp) {
                    $student = $sp->getStudent();
                    $list[] = [
                        'student' => $student->getFirstName() . ' ' . $student->getLastName(),
                        'label' => $sp->getLabel(),
                        'amount' => $sp->getAmount(),
                    ];
                }

                $html = $this->twig->render('email/class_council/settlement_confirmation.html.twig', [
                    'items' => $list,
                ]);

                $email = new Email()
                    ->to($to)
                    ->subject('Potwierdzenie rozliczenia składek')
                    ->html($html);

                $this->mailer->send($email);
            }
        } catch (\Throwable) {
            // ignore mail errors for now
        }
    }
}
