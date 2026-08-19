<?php

declare(strict_types=1);

namespace App\Entity;

enum MenuHookLinkTarget: string
{
    case POST = 'post';
    case WORKSHOP = 'workshop';
    case URL = 'url';
}
