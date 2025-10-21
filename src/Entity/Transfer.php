<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Transfer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        if ($this->payment === $payment) {
            return $this;
        }

        // Detach from previous payment's collection
        if ($this->payment !== null) {
            $prev = $this->payment;
            // Use getter to access collection and remove without triggering recursion
            if ($prev->getTransfers()->contains($this)) {
                $prev->getTransfers()
                    ->removeElement($this);
            }
        }

        $this->payment = $payment;

        // Attach to new payment's collection to keep bidirectional sync
        if ($payment !== null && ! $payment->getTransfers()->contains($this)) {
            $payment->getTransfers()
                ->add($this);
        }

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
