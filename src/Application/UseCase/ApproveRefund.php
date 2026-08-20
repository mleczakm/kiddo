<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Repository\PaymentRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Canonical use case for an admin approving a requested refund. Extracted
 * from ReservationDetailsModal, which previously applied the payment
 * workflow transition inline - this makes the authorization and transition
 * logic reachable from any future entry point (chat tools, console, a
 * future admin API), not only that one Live Component.
 */
final readonly class ApproveRefund
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private UserRepositoryInterface $userRepository,
        private PaymentStateMachineInterface $paymentStateMachine,
        private RoleHierarchyInterface $roleHierarchy,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Ulid $paymentId, int $approvedByUserId, ?string $note): void
    {
        $approvedBy = $this->userRepository->find($approvedByUserId);
        if ($approvedBy === null) {
            throw new \InvalidArgumentException(sprintf('User %d not found', $approvedByUserId));
        }

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($approvedBy->getRoles());
        if (!in_array('ROLE_MANAGE_BOOKINGS', $reachableRoles, true)) {
            throw new \RuntimeException('User is not authorized to approve refunds');
        }

        $payment = $this->paymentRepository->find($paymentId);
        if ($payment === null) {
            throw new \InvalidArgumentException(sprintf('Payment %s not found', $paymentId));
        }

        if (!$this->paymentStateMachine->can($payment, Payment::TRANSITION_REFUND)) {
            throw new \RuntimeException('This payment cannot be refunded in its current state');
        }

        $this->paymentStateMachine->apply($payment, Payment::TRANSITION_REFUND);
        $payment->recordStatusDecision($approvedBy, $note);
        $this->em->flush();
    }
}
