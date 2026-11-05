<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Command\RunsOutsideTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Messenger\DoctrineTransactionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Symfony's doctrine_transaction middleware, except that messages implementing
 * {@see RunsOutsideTransaction} are handled without the wrapping transaction.
 */
final class OptionalDoctrineTransactionMiddleware extends DoctrineTransactionMiddleware
{
    /**
     * @throws ExceptionInterface
     */
    #[\Override]
    protected function handleForManager(
        EntityManagerInterface $entityManager,
        Envelope $envelope,
        StackInterface $stack,
    ): Envelope {
        if ($envelope->getMessage() instanceof RunsOutsideTransaction) {
            return $stack->next()->handle($envelope, $stack);
        }

        return parent::handleForManager($entityManager, $envelope, $stack);
    }
}
