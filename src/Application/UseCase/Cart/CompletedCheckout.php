<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Domain\Commerce\Order\CustomerOrder;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class CompletedCheckout
{
    public function __construct(
        public CustomerOrder $order,
        public ?string $paymentCode,
    ) {}
}
