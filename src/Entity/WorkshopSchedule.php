<?php

namespace App\Entity;

use App\Repository\WorkshopScheduleRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WorkshopScheduleRepository::class)]
class WorkshopSchedule
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: WorkshopTemplate::class, inversedBy: 'schedules')]
    #[ORM\JoinColumn(nullable: false)]
    private WorkshopTemplate $template;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $startDate;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $endDate;

    #[ORM\Column(type: 'json_document', options: ['jsonb' => true])]
    private mixed $strategy;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $occurrenceOverrides = []; // e.g. ['2024-05-20T10:00:00+02:00' => ['modified' => true, 'occurenceId' => '...']]

    #[ORM\OneToMany(mappedBy: 'schedule', targetEntity: WorkshopOccurrence::class)]
    private Collection $occurrences;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->occurrences = new ArrayCollection();
    }
}
