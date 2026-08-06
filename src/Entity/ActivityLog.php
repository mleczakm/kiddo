<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_activity_log_created_at')]
#[ORM\Index(columns: ['dedupe_key'], name: 'idx_activity_log_dedupe_key')]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Idempotency key. Recurring background sweeps (e.g. re-checking unmatched
     * transfers) may report the same real-world event more than once; when set,
     * a second entry with the same key is skipped rather than duplicating the feed.
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $dedupeKey = null;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        #[ORM\Column(type: 'string', length: 50, enumType: ActivityType::class)]
        private ActivityType $type,
        #[ORM\Column(type: 'string', length: 255)]
        private string $title,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $subject = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $summary = null,
        #[ORM\Column(type: 'string', length: 512, nullable: true)]
        private ?string $url = null,
        #[ORM\Column(type: 'json', nullable: true)]
        private array $context = [],
        ?string $dedupeKey = null,
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable('now');
        $this->dedupeKey = $dedupeKey;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getType(): ActivityType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubject(): ?User
    {
        return $this->subject;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDedupeKey(): ?string
    {
        return $this->dedupeKey;
    }
}
