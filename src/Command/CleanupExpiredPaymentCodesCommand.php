<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PaymentCodeManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CleanupExpiredPaymentCodesCommand extends Command
{
    protected static $defaultName = 'app:payment-codes:cleanup';
    protected static $defaultDescription = 'Remove expired payment codes';

    public function __construct(
        private PaymentCodeManager $paymentCodeManager,
        private int $paymentCodeLifetime = 86400, // 24 hours in seconds
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run (do not make any changes)')
            ->addOption('lifetime', null, InputOption::VALUE_REQUIRED, 'Payment code lifetime in seconds', $this->paymentCodeLifetime)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $lifetime = (int) $input->getOption('lifetime');
        
        $expiryDate = (new \DateTimeImmutable())->modify("-$lifetime seconds");
        
        $io->note(sprintf('Removing payment codes older than %s', $expiryDate->format('Y-m-d H:i:s')));
        
        if ($dryRun) {
            $io->note('Dry run - no changes will be made');
            return Command::SUCCESS;
        }
        
        $count = $this->paymentCodeManager->cleanupExpiredCodes($expiryDate);
        
        $io->success(sprintf('Removed %d expired payment codes', $count));
        
        return Command::SUCCESS;
    }
}
