<?php

declare(strict_types=1);

namespace App\Entity;

enum PostVisibility: string
{
    case PUBLIC = 'public';
    case LOGGED_IN = 'logged_in';
    case STAFF_ONLY = 'staff_only';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Publiczny',
            self::LOGGED_IN => 'Tylko zalogowani',
            self::STAFF_ONLY => 'Tylko dla zespołu',
        };
    }
}
