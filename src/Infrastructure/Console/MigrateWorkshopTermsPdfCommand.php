<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Repository\LessonMetadataRepositoryInterface;
use App\Application\Service\WorkshopFileManager;
use App\Application\Service\WorkshopTermsPdfProvider;
use App\Entity\LessonMetadata;
use App\Entity\WorkshopFileRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-off backfill: the workshop terms-of-use PDF used to be a single
 * hardcoded static file (public/docs/Regulamin.pdf) shown for every
 * workshop. Now that it's a real per-workshop attachment
 * (WorkshopFileRole::TERMS_OF_USE), this stores it once as a reusable File
 * and attaches that same row to every workshop missing a terms attachment —
 * one stored copy shared across every LessonMetadata via WorkshopFile join
 * rows, not one upload per workshop.
 */
#[AsCommand(
    name: 'app:workshop:migrate-terms-pdf',
    description: 'Attaches the static Regulamin.pdf as a terms-of-use file to every workshop missing one',
)]
final class MigrateWorkshopTermsPdfCommand extends Command
{
    /** @throws \Symfony\Component\Console\Exception\LogicException */
    public function __construct(
        private readonly LessonMetadataRepositoryInterface $lessonMetadataRepository,
        private readonly WorkshopFileManager $workshopFileManager,
        private readonly WorkshopTermsPdfProvider $termsPdfProvider,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    /** @throws \Symfony\Component\Console\Exception\InvalidArgumentException */
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be attached without saving');
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \DomainException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->termsPdfProvider->exists()) {
            $io->error("Terms PDF not found at {$this->termsPdfProvider->getPath()}.");

            return Command::FAILURE;
        }

        $missing = array_values(array_filter(
            $this->lessonMetadataRepository->findAll(),
            static fn(LessonMetadata $metadata) => $metadata->getTermsAttachment() === null,
        ));

        if ($missing === []) {
            $io->success('Every workshop already has a terms-of-use attachment.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('%d workshop(s) missing a terms-of-use attachment.', count($missing)));

        if ($input->getOption('dry-run')) {
            $io->note('Dry run: nothing was saved.');

            return Command::SUCCESS;
        }

        $file = $this->termsPdfProvider->findOrStore();

        foreach ($missing as $metadata) {
            $this->workshopFileManager->attachExisting(
                $metadata,
                $file,
                WorkshopFileRole::TERMS_OF_USE,
                $metadata->files->count(),
            );
        }
        $this->entityManager->flush();

        $io->success(sprintf('Attached the terms-of-use PDF to %d workshop(s).', count($missing)));

        return Command::SUCCESS;
    }
}
