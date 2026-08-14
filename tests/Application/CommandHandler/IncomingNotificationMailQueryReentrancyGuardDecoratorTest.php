<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use PHPUnit\Framework\Assert;
use App\Application\CommandHandler\IncomingNotificationMailQuery;
use App\Application\CommandHandler\IncomingNotificationMailQueryReentrancyGuardDecorator;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class IncomingNotificationMailQueryReentrancyGuardDecoratorTest extends TestCase
{
    public function testInvokeYieldsFromDecorated(): void
    {
        $one = $this->createMock(MessageQueryInterface::class);
        $two = $this->createMock(MessageQueryInterface::class);

        $decorated = new readonly class ([$one, $two]) implements IncomingNotificationMailQuery {
            /**
             * @param list<MessageQueryInterface> $messages
             */
            public function __construct(
                private array $messages
            ) {}

            public function __invoke(): iterable
            {
                yield from $this->messages;
            }
        };

        $guard = new IncomingNotificationMailQueryReentrancyGuardDecorator($decorated);

        self::assertSame([$one, $two], iterator_to_array($guard(), false));
    }

    public function testOverlappingInvocationYieldsNothingInsteadOfRacing(): void
    {
        $message = $this->createMock(MessageQueryInterface::class);

        $decorated = new class ($message) implements IncomingNotificationMailQuery {
            public function __construct(
                private readonly MessageQueryInterface $message
            ) {}

            /**
             * @var list<IncomingNotificationMailQueryReentrancyGuardDecorator>
             */
            public array $reentrantCallers = [];

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

        $guard = new IncomingNotificationMailQueryReentrancyGuardDecorator($decorated);
        $decorated->reentrantCallers = [$guard];

        self::assertSame([$message], iterator_to_array($guard(), false));
    }

    public function testRunningFlagResetsAfterExceptionSoLaterCallsAreNotSkipped(): void
    {
        $message = $this->createMock(MessageQueryInterface::class);

        $decorated = new class ($message) implements IncomingNotificationMailQuery {
            private int $calls = 0;

            public function __construct(
                private readonly MessageQueryInterface $message
            ) {}

            public function __invoke(): iterable
            {
                ++$this->calls;

                if ($this->calls === 1) {
                    throw new \RuntimeException('boom');
                }

                yield $this->message;
            }
        };

        $guard = new IncomingNotificationMailQueryReentrancyGuardDecorator($decorated);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        try {
            iterator_to_array($guard(), false);
        } finally {
            self::assertSame([$message], iterator_to_array($guard(), false));
        }
    }
}
