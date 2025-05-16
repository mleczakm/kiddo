<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Lesson
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'json_document', options: [
        'jsonb' => true,
    ])]
    private LessonMetadata $metadata;

    #[ORM\Column(type: 'string', nullable: false)]
    public string $status;

    public WorkshopType $type = WorkshopType::WEEKLY;

    public function __construct(LessonMetadata $metadata)
    {
        $this->id = new Ulid();
        $this->metadata = $metadata;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getMetadata(): LessonMetadata
    {
        return $this->metadata;
    }

    public function setMetadata(LessonMetadata $metadata): void
    {
        $this->metadata = $metadata;
    }
}
