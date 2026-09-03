<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Command\IssueSubscriptionCharges;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:subscriptions:issue-charges',
    description: 'Issue this month\'s charge for every active monthly subscription',
)]
final class IssueSubscriptionChargesCommand extends Command
{
    /** @throws \Symfony\Component\Console\Exception\LogicException */
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    /** @throws \Symfony\Component\Console\Exception\InvalidArgumentException */
    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'period',
            null,
            InputOption::VALUE_REQUIRED,
            'Billing period (any date in the month), e.g. 2026-10-01',
        );
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $period */
        $period = $input->getOption('period');
        $when = $period !== null ? new \DateTimeImmutable($period) : new \DateTimeImmutable();

        $this->bus->dispatch(new IssueSubscriptionCharges($when));

        $output->writeln(sprintf('Dispatched IssueSubscriptionCharges for %s.', $when->format('Y-m')));

        return self::SUCCESS;
    }
}
