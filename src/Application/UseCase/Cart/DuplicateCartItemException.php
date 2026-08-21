<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

/**
 * Thrown by AddCartItem/ChangeCartParticipant when the resulting item would
 * select the same lesson/ticket-type/participant as one already in the
 * cart - the explicit duplicate-item rule from Stage 10 of the commerce
 * rollout plan (each item represents one ticket/participant, so a second
 * identical selection is a mistake to reject, not silently merge).
 */
final class DuplicateCartItemException extends \LogicException {}
