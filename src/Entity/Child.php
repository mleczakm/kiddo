<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChildRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChildRepository::class)]
class Child
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
        #[ORM\Column(length: 120)]
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 120)]
        private string $name,
        #[ORM\Column(type: 'date_immutable', nullable: true)]
        #[Assert\LessThan('today')]
        private ?\DateTimeImmutable $birthday
    ) {
        $this->id = new Ulid();
        $this->createdAt = Clock::get()->now();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getBirthday(): ?\DateTimeImmutable
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeImmutable $birthday): self
    {
        $this->birthday = $birthday;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAgeYears(?\DateTimeImmutable $asOf = null): ?int
    {
        if ($this->birthday === null) {
            return null;
        }
        $asOf ??= Clock::get()->now();
        $interval = $this->birthday->diff($asOf);
        return (int) $interval->y;
    }
}
