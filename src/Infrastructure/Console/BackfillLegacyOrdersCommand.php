<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Service\LegacyOrderBackfill;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:orders:backfill-legacy')]
final class BackfillLegacyOrdersCommand extends Command
{
    /** @throws \Symfony\Component\Console\Exception\LogicException */
    public function __construct(
        private readonly LegacyOrderBackfill $backfill,
    ) {
        parent::__construct();
    }

    /** @throws \Symfony\Component\Console\Exception\InvalidArgumentException */
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum payments in this batch', '100');
    }

    /**
     * @throws \ArithmeticError
     * @throws \Brick\Math\Exception\MathException
     * @throws \DivisionByZeroError
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->backfill->run(max(1, (int) $input->getOption('limit')));
        foreach ($report as $name => $value) {
            $output->writeln(sprintf('%s: %d', $name, $value));
        }
        return $report['amountDifferenceMinor'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
