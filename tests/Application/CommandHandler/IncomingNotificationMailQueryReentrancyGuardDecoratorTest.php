<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use App\Application\CommandHandler\IncomingNotificationMailQueryReentrancyGuardDecorator;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[Group('unit')]
final class IncomingNotificationMailQueryReentrancyGuardDecoratorTest extends TestCase
{
    public function testInvokeYieldsFromDecorated(): void
    {
        $one = $this->createMock(MessageQueryInterface::class);
        $two = $this->createMock(MessageQueryInterface::class);

        $decorated = new readonly class([$one, $two]) implements IncomingNotificationMailQuery {
            /**
             * @param list<MessageQueryInterface> $messages
             */
            public function __construct(
                private array $messages,
            ) {}

            #[\Override]
            public function __invoke(): iterable
            {
                yield from $this->messages;
            }
        };

        $guard = $this->createGuard($decorated);

        static::assertSame([$one, $two], iterator_to_array($guard(), false));
    }

    public function testOverlappingInvocationYieldsNothingInsteadOfRacing(): void
    {
        $message = $this->createMock(MessageQueryInterface::class);

        $decorated = new class($message) implements IncomingNotificationMailQuery {
            public function __construct(
                private readonly MessageQueryInterface $message,
            ) {}

            /**
             * @var list<IncomingNotificationMailQueryReentrancyGuardDecorator>
             */
            public array $reentrantCallers = [];

            #[\Override]
            public function __invoke(): iterable
            {
                // Simulates a second scheduled run starting while this one is still
                // mid-flight - exactly what happened when a slow IMAP round-trip
                // outlasted the 30s polling interval.
                foreach ($this->reentrantCallers as $caller) {
                    Assert::assertSame([], iterator_to_array($caller(), false));
                }

                yield $this->message;
            }
        };

        $lockFactory = new LockFactory(new InMemoryStore());
        $guard = new IncomingNotificationMailQueryReentrancyGuardDecorator($decorated, $lockFactory);
        // A separate decorator instance models another task-worker/container scope.
        $decorated->reentrantCallers = [
            new IncomingNotificationMailQueryReentrancyGuardDecorator($decorated, $lockFactory),
        ];

        static::assertSame([$message], iterator_to_array($guard(), false));
    }

    public function testRunningFlagResetsAfterExceptionSoLaterCallsAreNotSkipped(): void
    {
        $message = $this->createMock(MessageQueryInterface::class);

        $decorated = new class($message) implements IncomingNotificationMailQuery {
            private int $calls = 0;

            public function __construct(
                private readonly MessageQueryInterface $message,
            ) {}

            #[\Override]
            public function __invoke(): iterable
            {
                ++$this->calls;

                if ($this->calls === 1) {
                    throw new \RuntimeException('boom');
                }

                yield $this->message;
            }
        };

        $guard = $this->createGuard($decorated);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        try {
            iterator_to_array($guard(), false);
        } finally {
            static::assertSame([$message], iterator_to_array($guard(), false));
        }
    }

    private function createGuard(IncomingNotificationMailQuery $decorated): IncomingNotificationMailQueryReentrancyGuardDecorator
    {
        return new IncomingNotificationMailQueryReentrancyGuardDecorator(
            $decorated,
            new LockFactory(new InMemoryStore()),
        );
    }
}
