<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(columns: ['user_id', 'read_at', 'deleted_at', 'created_at'], name: 'idx_notification_user_state_created')]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(enumType: NotificationSeverity::class)]
    private NotificationSeverity $severity = NotificationSeverity::Info;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Tenant::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Tenant $tenant,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(type: 'string', length: 255)]
        private string $title,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $body = null,
        #[ORM\Column(type: 'string', length: 512, nullable: true)]
        private ?string $url = null,
        ?NotificationSeverity $severity = null,
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable('now');
        if ($severity !== null) {
            $this->severity = $severity;
        }
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): void
    {
        $this->body = $body;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getSeverity(): NotificationSeverity
    {
        return $this->severity;
    }

    public function setSeverity(NotificationSeverity $severity): void
    {
        $this->severity = $severity;
    }

    public function markRead(?\DateTimeImmutable $at = null): void
    {
        $this->readAt = $at ?? new \DateTimeImmutable('now');
    }

    public function softDelete(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = $at ?? new \DateTimeImmutable('now');
    }

    public function isUnread(): bool
    {
        return $this->readAt === null && $this->deletedAt === null;
    }
}
