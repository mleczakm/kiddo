<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketType;
use App\Entity\TicketReschedulePolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:migrate-ticket-options',
    description: 'Migruje istniejące opcje biletów do nowego formatu (description, reschedulePolicy)'
)]
class MigrateTicketOptionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migracja opcji biletów do nowego formatu');

        // Wczytaj tłumaczenia opisów biletów
        $translations = Yaml::parseFile(__DIR__ . '/../../translations/messages+intl-icu.pl.yaml');
        $carnetDesc = $translations['lesson']['carnet_4']['description'] ?? 'Karnet na 4 wejścia';
        $oneTimeDesc = $translations['lesson']['one_time']['description'] ?? 'Bilet jednorazowy';

        $seriesRepo = $this->em->getRepository(Series::class);
        $updated = 0;

        // Migracja w Series
        /** @var Series $series */
        foreach ($seriesRepo->findAll() as $series) {
            $newOptions = [];
            foreach ($series->ticketOptions as $option) {
                $desc = $option->type === TicketType::CARNET_4 ? $carnetDesc : ($option->type === TicketType::ONE_TIME ? $oneTimeDesc : 'Bilet');
                $newOptions[] = new TicketOption(
                    $option->type,
                    $option->price,
                    $desc,
                    TicketReschedulePolicy::UNLIMITED_24H_BEFORE
                );
                $updated++;
            }
            $series->ticketOptions = $newOptions;
        }

        $this->em->flush();
        $io->success("Zaktualizowano {$updated} opcji biletów.");
        return Command::SUCCESS;
    }
}
