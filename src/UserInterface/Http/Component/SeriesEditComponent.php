<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Repository\SeriesRepository;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Uid\Ulid;

#[AsLiveComponent]
final class SeriesEditComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly SeriesRepository $seriesRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[LiveProp(writable: true)]
    public string $seriesId = '';

    /**
     * @var list<array{type: string, amount: string, currency: string, description: string, reschedulePolicy: string}>
     */
    #[LiveProp(writable: true)]
    public array $options = [];

    public function mount(): void
    {
        if ($this->seriesId !== '') {
            $this->loadFromSeries($this->seriesId);
        }
    }

    #[LiveAction]
    public function loadFromSeries(#[LiveArg] string $seriesId): void
    {
        $series = $this->findSeries($seriesId);
        if (! $series) {
            return;
        }

        $this->seriesId = $seriesId;
        $this->options = [];
        foreach ($series->ticketOptions as $opt) {
            // $opt is TicketOption
            $this->options[] = [
                'type' => $opt->type->value,
                'amount' => $opt->price->getAmount()
                    ->__toString(),
                'currency' => $opt->price->getCurrency()
                    ->getCurrencyCode(),
                'description' => $opt->description,
                'reschedulePolicy' => $opt->reschedulePolicy->value,
            ];
        }
    }

    /**
     * @return list<string>
     */
    public function getTicketTypes(): array
    {
        return array_map(static fn(TicketType $e) => $e->value, TicketType::cases());
    }

    /**
     * @return list<string>
     */
    public function getReschedulePolicies(): array
    {
        return array_map(static fn(TicketReschedulePolicy $e) => $e->value, TicketReschedulePolicy::cases());
    }

    #[LiveAction]
    public function addOption(): void
    {
        $this->options[] = [
            'type' => TicketType::ONE_TIME->value,
            'amount' => '0.00',
            'currency' => 'PLN',
            'description' => '',
            'reschedulePolicy' => TicketReschedulePolicy::UNLIMITED_24H_BEFORE->value,
        ];
    }

    #[LiveAction]
    public function removeOption(#[LiveArg] int $index): void
    {
        if (isset($this->options[$index])) {
            array_splice($this->options, $index, 1);
        }
    }

    #[LiveAction]
    public function cancelEdit(): void
    {
        // no-op placeholder to satisfy the cancel button; parent container controls visibility
    }

    #[LiveAction]
    public function save(): void
    {
        $series = $this->findSeries($this->seriesId);
        if (! $series) {
            return;
        }

        $ticketOptions = [];
        foreach ($this->options as $row) {
            $type = TicketType::from($row['type']);
            $currency = $row['currency'] !== '' ? $row['currency'] : 'PLN';
            // Normalize amount: ensure dot decimal and 2 fraction digits
            $amount = $row['amount'] !== '' ? $row['amount'] : '0.00';
            $money = Money::of($amount, $currency);
            $description = $row['description'];
            $policy = TicketReschedulePolicy::from($row['reschedulePolicy']);
            $ticketOptions[] = new TicketOption($type, $money, $description, $policy);
        }

        $series->ticketOptions = $ticketOptions;
        $this->em->flush();

        // No redirect; parent can hide editor with its own action.
    }

    private function findSeries(string $seriesId): ?Series
    {
        try {
            $id = Ulid::fromString($seriesId);
        } catch (\Throwable) {
            return null;
        }
        $series = $this->seriesRepository->find($id);
        return $series instanceof Series ? $series : null;
    }
}
