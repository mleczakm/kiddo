<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Domain\Commerce\Order\BuyerDetails;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class OrderPlacementOptions
{
    private function __construct(
        public bool $writeOrder,
        public BuyerDetails $buyerDetails,
    ) {}

    public static function standard(): self
    {
        return new self(true, BuyerDetails::privateCustomer());
    }

    public static function withoutOrder(): self
    {
        return new self(false, BuyerDetails::privateCustomer());
    }

    public static function forBuyer(BuyerDetails $buyerDetails): self
    {
        return new self(true, $buyerDetails);
    }
}
