<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Transfer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'transfers')]
    #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Payment $payment = null;

    public function __construct(
        #[ORM\Column(type: 'string', length: 255)]
        private string $accountNumber,
        #[ORM\Column(type: 'string', length: 255)]
        private string $sender,
        #[ORM\Column(type: 'string', length: 255)]
        public string $title,
        #[ORM\Column(type: 'string', length: 255)]
        public string $amount,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $transferredAt
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;
        return $this;
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getTransferredAt(): \DateTimeImmutable
    {
        return $this->transferredAt;
    }
}
