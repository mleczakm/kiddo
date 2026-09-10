<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\GlobalSearchQuery;
use Ds\PriorityQueue;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;

/**
 * Merges the database-backed global search with the in-memory help-centre index.
 * When the {@code help_center} flag is off it is a transparent pass-through to the
 * database query, so search behaves exactly as before.
 */
final readonly class CompositeGlobalSearchQuery implements GlobalSearchQuery
{
    public function __construct(
        private PostgresGlobalSearchQuery $database,
        private DocSearchIndex $docs,
        private FeatureManager $featureManager,
    ) {}

    #[\Override]
    public function search(string $query, int $limit = 15): PriorityQueue
    {
        $queue = $this->database->search($query, $limit);

        if (!$this->featureManager->isEnabled('help_center')) {
            return $queue;
        }

        foreach ($this->docs->search($query, $limit) as $scored) {
            $queue->push($scored->reference, $scored->priority);
        }

        return $queue;
    }
}
