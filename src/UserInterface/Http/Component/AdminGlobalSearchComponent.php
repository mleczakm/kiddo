<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Search\GlobalSearchQuery;
use App\Application\Search\SearchReference;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchType;
use App\Infrastructure\Search\SearchResultHydrator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminGlobalSearchComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(
        private readonly GlobalSearchQuery $search,
        private readonly SearchResultHydrator $hydrator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Connection $connection,
    ) {}

    /**
     * @return list<SearchResult>
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @throws \Doctrine\DBAL\Exception
     * @throws \ValueError
     * @throws \Brick\Math\Exception\NumberFormatException
     * @throws \Brick\Math\Exception\RoundingNecessaryException
     * @throws \Brick\Money\Exception\UnknownCurrencyException
     * @throws \InvalidArgumentException
     */
    public function getResults(): array
    {
        $this->denyAccessUnlessGranted('ROLE_HOST');
        $references = [];
        foreach ($this->search->search($this->query) as $reference) {
            $references[] = $reference;
        }

        return $this->hydrator->hydrate($references);
    }

    /**
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     * @throws \Doctrine\DBAL\Exception
     * @throws \RuntimeException
     */
    public function resultUrl(SearchReference $reference): string
    {
        return match ($reference->type) {
            SearchType::Client, SearchType::Child => $this->urlGenerator->generate('app_admin_user_view', [
                'id' => $reference->type === SearchType::Client ? $reference->id : $this->childOwnerId($reference->id),
            ]),
            SearchType::Lesson => $this->urlGenerator->generate('app_admin_lesson_view', [
                'id' => Ulid::fromString($reference->id)->toBase32(),
            ]),
            SearchType::Booking => $this->urlGenerator->generate('app_admin_bookings', [
                'search' => $reference->id,
            ]),
            SearchType::Payment, SearchType::Transfer => $this->urlGenerator->generate('app_admin_transfers', [
                'search' => $reference->id,
            ]),
            SearchType::Post => $this->urlGenerator->generate('app_admin_post_edit', [
                'id' => Ulid::fromString($reference->id)->toBase32(),
            ]),
            SearchType::Recommendation => $this->urlGenerator->generate('app_admin_settings', [
                'tab' => 'recommendations',
                '_fragment' => 'recommendation-' . $reference->id,
            ]),
            SearchType::Doc => $this->urlGenerator->generate('app_admin_help_article', [
                'slug' => $reference->id,
            ]),
        };
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \RuntimeException
     */
    private function childOwnerId(string $childId): string
    {
        $ownerId = $this->connection->fetchOne('SELECT owner_id FROM child WHERE id = :id', [
            'id' => $childId,
        ]);

        if (!is_int($ownerId) && !is_string($ownerId)) {
            throw new \RuntimeException('Child owner not found.');
        }

        return (string) $ownerId;
    }
}
