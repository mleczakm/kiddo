<?php

declare(strict_types=1);

namespace App\Entity\ClassCouncil;

use App\Entity\Payment;
use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class StudentPayment
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIAL = 'partial';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: ClassRoom::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ClassRoom $classRoom;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payment $payment = null;

    #[ORM\Column(type: 'string', length: 16, options: [
        'default' => 'pending',
    ])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Student::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Student $student,
        #[ORM\Column(type: 'string', length: 128)]
        private string $label,
        #[ORM\Column(type: 'json_document')]
        private Money $amount
    ) {
        $this->id = new Ulid();
        $this->classRoom = $this->student->getClassRoom();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getStudent(): Student
    {
        return $this->student;
    }

    public function getClassRoom(): ClassRoom
    {
        return $this->classRoom;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): void
    {
        $this->dueAt = $dueAt;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): void
    {
        $this->payment = $payment;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): void
    {
        $this->paidAt = $paidAt;
    }

    public function markPaid(): void
    {
        $this->status = self::STATUS_PAID;
        $this->paidAt = new \DateTimeImmutable();
    }
}
