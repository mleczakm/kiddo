<?php

declare(strict_types=1);

namespace App\Entity;

class Carnet
{
    public function __construct(public readonly int $entries)
    {
        
    }
}
