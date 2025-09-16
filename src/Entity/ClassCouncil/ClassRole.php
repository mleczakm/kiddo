<?php

declare(strict_types=1);

namespace App\Entity\ClassCouncil;

enum ClassRole: string
{
    case TREASURER = 'treasurer';
    case PRESIDENT = 'president';
    case VICE_PRESIDENT = 'vice_president';
    case PARENT = 'parent';
}
