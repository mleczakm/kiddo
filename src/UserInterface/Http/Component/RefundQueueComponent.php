<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Entity\RefundRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Admin refund queue: pending refund requests, newest first, not restricted
 * to the payment week AdminPaymentsListComponent filters by. Rows open the
 * same ReservationDetailsModal used elsewhere for the actual approve/decline
 * actions, so that logic lives in exactly one place.
 */
#[AsLiveComponent('RefundQueueComponent', template: 'components/RefundQueueComponent.html.twig')]
final class RefundQueueComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly RefundRequestRepositoryInterface $refundRequestRepository,
    ) {}

    /**
     * @return list<RefundRequest>
     */
    public function getPendingRequests(): array
    {
        return $this->refundRequestRepository->findPendingQueue();
    }

    public function getPendingCount(): int
    {
        return $this->refundRequestRepository->countPending();
    }
}
