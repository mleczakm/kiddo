<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Throwable;

class Scheduler
{
    /**
     * @var array<MessageGenerator>
     */
    private array $generators = [];

    private int $index = 0;

    /**
     * @param list<ScheduleProviderInterface> $scheduleProviders
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
        array $scheduleProviders,
        private readonly ClockInterface $clock = new Clock(),
        private readonly ?EventDispatcherInterface $dispatcher = null
    ) {

        foreach ($scheduleProviders as $scheduleProvider) {
            $this->addSchedule($scheduleProvider->getSchedule());
        }
    }

    public function addSchedule(Schedule $schedule): void
    {
        $this->addMessageGenerator(new MessageGenerator($schedule, 'schedule_' . $this->index++, $this->clock));
    }

    public function addMessageGenerator(MessageGenerator $generator): void
    {
        $this->generators[] = $generator;
    }

    public function run(): void
    {

        foreach ($this->generators as $generator) {
            foreach ($generator->getMessages() as $context => $message) {
                if (! $this->dispatcher) {
                    $this->bus->dispatch($message);

                    continue;
                }

                $preRunEvent = new PreRunEvent($generator->getSchedule(), $context, $message);
                $this->dispatcher->dispatch($preRunEvent);

                if ($preRunEvent->shouldCancel()) {
                    continue;
                }

                try {
                    $this->bus->dispatch($message);

                    $this->dispatcher->dispatch(new PostRunEvent($generator->getSchedule(), $context, $message));
                } catch (Throwable $error) {
                    $failureEvent = new FailureEvent($generator->getSchedule(), $context, $message, $error);
                    $this->dispatcher->dispatch($failureEvent);

                    if (! $failureEvent->shouldIgnore()) {
                        throw $error;
                    }
                }
            }
        }
    }
}
