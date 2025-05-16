<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class WorkshopOccurrence
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: WorkshopSchedule::class, inversedBy: 'occurrences')]
    #[ORM\JoinColumn(nullable: false)]
    private WorkshopSchedule $schedule;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $start;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $end;

    #[ORM\Column(type: 'boolean')]
    private bool $isManuallyModified = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isVisible = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isCancelled = false;

    public function __construct()
    {
        $this->id = new Ulid();
    }
}
