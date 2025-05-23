<?php

declare(strict_types=1);

// src/Entity/Transfer.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Transfer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $accountNumber;

    #[ORM\Column(type: 'string', length: 255)]
    private string $sender;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'string', length: 255)]
    private string $amount;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $transferredAt;

    public function __construct(
        string $accountNumber,
        string $sender,
        string $title,
        string $amount,
        \DateTimeImmutable $transferredAt
    ) {
        $this->accountNumber = $accountNumber;
        $this->sender = $sender;
        $this->title = $title;
        $this->amount = $amount;
        $this->transferredAt = $transferredAt;
    }
}
