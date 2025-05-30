<?php

declare(strict_types=1);

namespace App\Entity;

use Brick\Money\Money;
use Brick\Money\MoneyBag;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class AwaitingPayment
{
    #[ORM\Id, ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\ManyToMany(targetEntity: Reservation::class)]
    private Collection $reservations;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json_document')]
    private MoneyBag $paidAmount;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        private User $user,
        #[ORM\Column(type: 'string', length: 4, unique: true)]
        private string $code,
        #[ORM\Column(type: 'json_document')]
        private Money $amount
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->reservations = new ArrayCollection();
        $this->paidAmount = new MoneyBag();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): void
    {
        if (! $this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
        }
    }

    public function removeReservation(Reservation $reservation): void
    {
        $this->reservations->removeElement($reservation);
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }
}
