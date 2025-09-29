<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Severity for in-app notifications.
 */
enum NotificationSeverity: string
{
    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
