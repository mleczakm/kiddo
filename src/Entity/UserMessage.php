<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'user_messages')]
class UserMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: MessageType::class)]
    private MessageType $type;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: MessageStatus::class)]
    private MessageStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $readBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Booking $relatedBooking = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Lesson $relatedLesson = null;

    public function __construct(
        User $user,
        string $subject,
        string $message,
        MessageType $type = MessageType::GENERAL
    ) {
        $this->id = new Ulid();
        $this->user = $user;
        $this->subject = $subject;
        $this->message = $message;
        $this->type = $type;
        $this->status = MessageStatus::UNREAD;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): MessageType
    {
        return $this->type;
    }

    public function setType(MessageType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): MessageStatus
    {
        return $this->status;
    }

    public function setStatus(MessageStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getReadBy(): ?User
    {
        return $this->readBy;
    }

    public function markAsRead(User $readBy): self
    {
        $this->status = MessageStatus::READ;
        $this->readAt = new \DateTimeImmutable();
        $this->readBy = $readBy;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): self
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }

    public function getRelatedBooking(): ?Booking
    {
        return $this->relatedBooking;
    }

    public function setRelatedBooking(?Booking $relatedBooking): self
    {
        $this->relatedBooking = $relatedBooking;
        return $this;
    }

    public function getRelatedLesson(): ?Lesson
    {
        return $this->relatedLesson;
    }

    public function setRelatedLesson(?Lesson $relatedLesson): self
    {
        $this->relatedLesson = $relatedLesson;
        return $this;
    }

    public function isUnread(): bool
    {
        return $this->status === MessageStatus::UNREAD;
    }
}
