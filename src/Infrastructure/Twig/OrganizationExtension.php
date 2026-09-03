<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Application\Service\OrganizationDetails;
use App\Application\Service\OrganizationDetailsProvider;
use Twig\Attribute\AsTwigFunction;

readonly class OrganizationExtension
{
    public function __construct(
        private OrganizationDetailsProvider $provider,
    ) {}

    #[AsTwigFunction('organization')]
    public function organization(): OrganizationDetails
    {
        return $this->provider->get();
    }
}
