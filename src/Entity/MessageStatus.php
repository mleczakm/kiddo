<?php

declare(strict_types=1);

namespace App\Entity;

enum MessageStatus: string
{
    case UNREAD = 'unread';
    case READ = 'read';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case ARCHIVED = 'archived';
}
