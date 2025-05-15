<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Ulid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class WorkshopTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'string')]
    private string $title;

    #[ORM\Column(type: 'string')]
    private string $lead;
    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'json_document')]
    private array $allowedTicketTypes = [];

    #[ORM\OneToMany(mappedBy: 'template', targetEntity: WorkshopSchedule::class)]
    private Collection $schedules;

    #[ORM\Column(type: 'json_document')]
    private WorkshopType $type = WorkshopType::WEEKLY;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->schedules = new ArrayCollection();
        $this->allowedTicketTypes = [WorkshopTicketType::CARNET, WorkshopTicketType::ONE_TIME];
    }

    public static function createOneTime()
    {
        
    }
}