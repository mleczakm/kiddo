<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\File\OrphanFileCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'files:cleanup-orphans', description: 'Remove unreferenced files older than the safety window')]
final class CleanupOrphanFilesCommand extends Command
{
    /** @throws \Symfony\Component\Console\Exception\LogicException */
    public function __construct(
        private readonly OrphanFileCleanupService $cleanupService,
    ) {
        parent::__construct();
    }

    /** @throws \Symfony\Component\Console\Exception\InvalidArgumentException */
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count orphans without deleting');
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orphanCount = $this->cleanupService->countOrphans();
        $eligible = $this->cleanupService->countEligibleForCleanup();

        $io->info("Found {$orphanCount} unreferenced files, {$eligible} eligible for cleanup.");

        if ($input->getOption('dry-run')) {
            $io->note('Dry run: no files deleted.');
            return Command::SUCCESS;
        }

        if ($eligible === 0) {
            $io->success('No orphaned files to clean up.');
            return Command::SUCCESS;
        }

        $deleted = $this->cleanupService->cleanup();
        $io->success("Deleted {$deleted} orphaned file(s).");

        return Command::SUCCESS;
    }
}
