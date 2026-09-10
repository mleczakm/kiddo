<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\ExpireWaitlistOffers;
use App\Application\Command\OfferWaitlistSeats;
use App\Application\Service\Waitlist\WaitlistService;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Expire waitlist offers past their hold window and re-offer each freed seat to
 * the next person in that lesson's queue.
 */
class ExpireWaitlistOffersHandler
{
    public function __construct(
        private readonly FeatureManager $featureManager,
        private readonly WaitlistService $waitlist,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function __invoke(ExpireWaitlistOffers $command): void
    {
        if (!$this->featureManager->isEnabled('waitlist')) {
            return;
        }

        $lessonIds = $this->waitlist->expireStaleOffers($command->referenceTime);
        foreach ($lessonIds as $lessonId) {
            $this->bus->dispatch(new OfferWaitlistSeats($lessonId));
        }

        if ($lessonIds !== []) {
            $this->logger->info('Waitlist offers expired', ['lessons' => count($lessonIds)]);
        }
    }
}
