<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Embeddable;

#[Embeddable]
class AgeRange
{
    public function __construct(
        #[ORM\Column(type: 'integer')]
        public readonly int $min,
        #[ORM\Column(type: 'integer')]
        public readonly ?int $max,
    ) {}
}
