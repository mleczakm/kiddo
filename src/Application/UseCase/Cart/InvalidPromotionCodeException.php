<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

/**
 * Thrown by ApplyPromotionCode when the normalized code matches no active
 * PricingRule::$promotionCode.
 */
final class InvalidPromotionCodeException extends \InvalidArgumentException {}
