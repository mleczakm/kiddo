<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Reservation
{
    #[ORM\Id, ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $reservedAt;

    public const STATUS_UNCONFIRMED = 'unconfirmed';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PAID = 'paid';

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    public Collection $awaitingPayments;

    private Collection $lessons;

    public function __construct(User $user)
    {
        $this->id = new Ulid();
        $this->user = $user;
        $this->reservedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_UNCONFIRMED;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getReservedAt(): \DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $allowed = [self::STATUS_UNCONFIRMED, self::STATUS_CONFIRMED, self::STATUS_PAID];
        if (! in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid reservation status');
        }
        $this->status = $status;
    }
}
