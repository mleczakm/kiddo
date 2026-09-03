<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\IssueSubscriptionCharges;
use App\Application\Repository\SubscriptionRepositoryInterface;
use App\Application\Service\Subscription\SubscriptionBillingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IssueSubscriptionChargesHandler
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SubscriptionBillingService $billing,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @throws \RuntimeException when a subscription's amount is invalid or no payment code can be allocated
     */
    public function __invoke(IssueSubscriptionCharges $command): void
    {
        $issued = 0;

        foreach ($this->subscriptionRepository->findAllActive() as $subscription) {
            $payment = $this->billing->chargeForPeriod($subscription, $command->period);
            $issued += $payment === null ? 0 : 1;
        }

        if ($issued > 0) {
            $this->em->flush();
        }
    }
}
