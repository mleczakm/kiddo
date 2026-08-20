<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'file')]
#[ORM\Index(columns: ['checksum'], name: 'idx_file_checksum')]
class File
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @throws \InvalidArgumentException */
    public function __construct(
        #[ORM\Column(type: 'string', length: 255)]
        private string $originalName,
        #[ORM\Column(type: 'string', length: 255)]
        private string $mimeType,
        #[ORM\Column(type: 'integer')]
        private int $size,
        #[ORM\Column(type: 'string', length: 64)]
        private string $checksum,
        #[ORM\Column(type: 'text')]
        private string $data,
    ) {
        if ($this->size < 0) {
            throw new \InvalidArgumentException('File size cannot be negative.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $this->checksum) !== 1) {
            throw new \InvalidArgumentException('File checksum must be a lowercase SHA-256 hash.');
        }

        $this->id = new Ulid();
        $this->createdAt = Clock::get()->now();
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    public function setUploadedBy(?User $uploadedBy): void
    {
        $this->uploadedBy = $uploadedBy;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
