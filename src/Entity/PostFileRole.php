<?php

declare(strict_types=1);

namespace App\Entity;

enum PostFileRole: string
{
    case COVER = 'cover';
    case INLINE = 'inline';
    case ATTACHMENT = 'attachment';
}
