<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Application\Command\RecordConsents;
use App\Application\Command\RevokeConsent;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * The one place consent commands are dispatched: recording an acceptance must
 * never break the flow that triggered it, so every dispatch here is wrapped and
 * a failure is logged, not thrown. The per-context managers keep only their own
 * "is this required / what is the wording" logic.
 */
final readonly class ConsentDispatcher
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger,
    ) {}

    public function record(User $user, ConsentSource $source, ConsentGrant ...$grants): void
    {
        $this->send(new RecordConsents($user, $source, $grants), $user, $source, false);
    }

    /**
     * Same as {@see record()} but deferred until the current bus finishes - use
     * from inside a message handler so the recording gets its own transaction.
     */
    public function recordDeferred(User $user, ConsentSource $source, ConsentGrant ...$grants): void
    {
        $this->send(new RecordConsents($user, $source, $grants), $user, $source, true);
    }

    public function revoke(User $user, ConsentType $type, ConsentSource $source): void
    {
        $this->send(new RevokeConsent($user, $type, $source), $user, $source, false);
    }

    private function send(object $message, User $user, ConsentSource $source, bool $deferred): void
    {
        try {
            $envelope = new Envelope($message);
            if ($deferred) {
                $envelope = $envelope->with(new DispatchAfterCurrentBusStamp());
            }
            $this->commandBus->dispatch($envelope);
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to dispatch consent command.', [
                'exception' => $exception,
                'command' => $message::class,
                'user_id' => $user->getId(),
                'source' => $source->value,
            ]);
        }
    }
}
