<?php

declare(strict_types=1);

namespace App\Entity;

enum WorkshopFileRole: string
{
    case ATTACHMENT = 'attachment';
    case TERMS_OF_USE = 'terms_of_use';
}
