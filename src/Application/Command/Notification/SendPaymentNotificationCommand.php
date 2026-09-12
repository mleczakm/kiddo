<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use Symfony\Component\Uid\Ulid;

/**
 * Carries the payment id, not the entity: this command is routed to the
 * `async` transport (see messenger.yaml), so it gets PHP-serialized and
 * later unserialized in a separate task-worker execution with its own
 * EntityManager. An entity object surviving that boundary comes back
 * detached — passing it into Doctrine relations (Notification#user) or
 * reading a typed, not-yet-initialized lazy property off it (e.g.
 * Child::$name in the email template) blows up. Fetch the Payment fresh
 * via the repository in the handler instead.
 */
readonly class SendPaymentNotificationCommand
{
    public function __construct(
        public Ulid $paymentId,
    ) {}
}
