<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\Notification\SendRefundDecisionNotificationCommand;
use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\ActivityLogger;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\ActivityType;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Canonical use case for an admin declining a requested refund. Moves the
 * payment back to "paid" - a declined request is not the same as never
 * having been requested, so the request itself is left in place (status
 * "declined") rather than deleted, and nothing here touches the booking or
 * its lesson cancellation state: declining a refund must not reactivate a
 * booking that was cancelled as part of the same original request.
 */
final readonly class DeclineRefund
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
    ) {}

    public function __invoke(Ulid $refundRequestId, int $declinedByUserId, ?string $note): void
    {
        $declinedBy = $this->userRepository->find($declinedByUserId);
        if ($declinedBy === null) {
            throw new \InvalidArgumentException(sprintf('User %d not found', $declinedByUserId));
        }

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($declinedBy->getRoles());
        if (!in_array('ROLE_MANAGE_BOOKINGS', $reachableRoles, true)) {
            throw new \RuntimeException('User is not authorized to decline refunds');
        }

        $refundRequest = $this->refundRequestRepository->find($refundRequestId);
        if ($refundRequest === null) {
            throw new \InvalidArgumentException(sprintf('Refund request %s not found', $refundRequestId));
        }

        if (!$refundRequest->isPending()) {
            throw new \RuntimeException('This refund request has already been decided');
        }

        $payment = $refundRequest->getPayment();
        if (!$this->paymentStateMachine->can($payment, Payment::TRANSITION_DECLINE_REFUND)) {
            throw new \RuntimeException('This payment cannot have its refund declined in its current state');
        }

        $this->paymentStateMachine->apply($payment, Payment::TRANSITION_DECLINE_REFUND);
        $payment->recordStatusDecision($declinedBy, $note);
        $refundRequest->decline($declinedBy, $note);

        $user = $refundRequest->getBooking()->getUser();
        $userId = $user->getId();
        $this->activityLogger->log(
            type: ActivityType::REFUND_DECLINED,
            title: sprintf('Zwrot dla %s został odrzucony', $user->getName()),
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
            new SendRefundDecisionNotificationCommand($refundRequest->getId(), approved: false),
        )->with(new DispatchAfterCurrentBusStamp()));
    }
}
