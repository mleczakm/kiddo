<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\OfferWaitlistSeats;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Service\Waitlist\WaitlistService;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Psr\Log\LoggerInterface;

/**
 * A seat freed on a lesson — offer it to the head of that lesson's waitlist.
 * Idempotent: {@see WaitlistService::offerSeats()} only fills genuine gaps.
 */
class OfferWaitlistSeatsHandler
{
    public function __construct(
        private readonly FeatureManager $featureManager,
        private readonly LessonRepositoryInterface $lessonRepository,
        private readonly WaitlistService $waitlist,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function __invoke(OfferWaitlistSeats $command): void
    {
        if (!$this->featureManager->isEnabled('waitlist')) {
            return;
        }

        $lesson = $this->lessonRepository->find($command->lessonId);
        if ($lesson === null || !$lesson->isWaitlistEnabled(true)) {
            return;
        }

        $offered = $this->waitlist->offerSeats($lesson);
        if ($offered > 0) {
            $this->logger->info('Waitlist seats offered', [
                'lessonId' => $command->lessonId->toRfc4122(),
                'offered' => $offered,
            ]);
        }
    }
}
