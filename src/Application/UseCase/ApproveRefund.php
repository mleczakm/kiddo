<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\Notification\SendRefundDecisionNotificationCommand;
use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\ActivityLogger;
use App\Application\Service\RefundBalanceCalculator;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\ActivityType;
use App\Entity\Payment;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        private RefundRequestRepositoryInterface $refundRequestRepository,
        private UserRepositoryInterface $userRepository,
        private PaymentStateMachineInterface $paymentStateMachine,
        private RoleHierarchyInterface $roleHierarchy,
        private ActivityLogger $activityLogger,
        private MessageBusInterface $bus,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em,
        private RefundBalanceCalculator $refundBalance,
    ) {}

    public function __invoke(
        Ulid $refundRequestId,
        int $approvedByUserId,
        ?string $note,
        ?int $approvedAmountMinor = null,
    ): void {
        $approvedBy = $this->userRepository->find($approvedByUserId);
        if ($approvedBy === null) {
            throw new \InvalidArgumentException(sprintf('User %d not found', $approvedByUserId));
        }

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($approvedBy->getRoles());
        if (!in_array('ROLE_MANAGE_BOOKINGS', $reachableRoles, true)) {
            throw new \RuntimeException('User is not authorized to approve refunds');
        }

        $refundRequest = $this->refundRequestRepository->find($refundRequestId);
        if ($refundRequest === null) {
            throw new \InvalidArgumentException(sprintf('Refund request %s not found', $refundRequestId));
        }

        if (!$refundRequest->isPending()) {
            throw new \RuntimeException('This refund request has already been decided');
        }

        $payment = $refundRequest->getPayment();
        if (
            $refundRequest->getBooking()->isCarnet()
            && $refundRequest->getOccurrence() !== null
            && $approvedAmountMinor === null
        ) {
            throw new \InvalidArgumentException('A carnet occurrence refund requires an explicit approved amount.');
        }

        $refundable = $this->refundBalance->refundableForBooking($payment, $refundRequest->getOrderLineId());
        $approvedAmountMinor ??= min($refundRequest->getRequestedAmountMinor(), $refundable);
        if ($approvedAmountMinor <= 0 || $approvedAmountMinor > $refundable) {
            throw new \InvalidArgumentException('Approved amount exceeds the refundable balance.');
        }

        $approvedBefore = $this->refundBalance->approved($payment);
        $fullyRefunded = ($approvedBefore + $approvedAmountMinor) === $payment->getAmount()->getMinorAmount()->toInt();
        $transition = $fullyRefunded ? Payment::TRANSITION_REFUND : Payment::TRANSITION_DECLINE_REFUND;
        if (!$this->paymentStateMachine->can($payment, $transition)) {
            throw new \RuntimeException('This payment cannot be refunded in its current state');
        }

        $this->paymentStateMachine->apply($payment, $transition);
        $payment->recordStatusDecision($approvedBy, $note);
        $refundRequest->approve(
            $approvedBy,
            $note,
            Money::ofMinor($approvedAmountMinor, $refundRequest->getCurrency()),
        );

        $user = $refundRequest->getBooking()->getUser();
        $userId = $user->getId();
        $this->activityLogger->log(
            type: ActivityType::REFUND_APPROVED,
            title: sprintf('Zwrot dla %s został zatwierdzony', $user->getName()),
            subject: $user,
            summary: $refundRequest->getLesson()?->getMetadata()->title,
            url: $userId !== null
                ? $this->urlGenerator->generate('app_admin_user_view', [
                    'id' => $userId,
                ]) : null,
            context: [
                'refundRequestId' => (string) $refundRequest->getId(),
                'paymentId' => (string) $payment->getId(),
            ],
        );

        $this->em->flush();

        $this->bus->dispatch(new Envelope(
            new SendRefundDecisionNotificationCommand($refundRequest->getId(), approved: true),
        )->with(new DispatchAfterCurrentBusStamp()));
    }
}
