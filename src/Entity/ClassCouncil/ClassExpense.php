<?php

declare(strict_types=1);

namespace App\Entity\ClassCouncil;

use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class ClassExpense
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $attachmentPath = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ClassRoom::class)]
        #[ORM\JoinColumn(nullable: false)]
        private ClassRoom $classRoom,
        #[ORM\Column(type: 'string', length: 255)]
        private string $label,
        #[ORM\Column(type: 'json_document')]
        private Money $amount,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $spentAt = new \DateTimeImmutable()
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getClassRoom(): ClassRoom
    {
        return $this->classRoom;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function setAmount(Money $amount): void
    {
        $this->amount = $amount;
    }

    public function getSpentAt(): \DateTimeImmutable
    {
        return $this->spentAt;
    }

    public function setSpentAt(\DateTimeImmutable $spentAt): void
    {
        $this->spentAt = $spentAt;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $path): void
    {
        $this->attachmentPath = $path;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
