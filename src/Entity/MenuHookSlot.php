<?php

declare(strict_types=1);

namespace App\Entity;

enum MenuHookSlot: string
{
    case MAIN_NAV_EXTRA = 'main_nav_extra';
    case MAIN_NAV_BEFORE_AUTH = 'main_nav_before_auth';
    case FOOTER_LINKS = 'footer_links';

    public function label(): string
    {
        return match ($this) {
            self::MAIN_NAV_EXTRA => 'Nav główna — po logowaniu',
            self::MAIN_NAV_BEFORE_AUTH => 'Nav główna — przed zalogowaniem',
            self::FOOTER_LINKS => 'Stopka strony',
        };
    }
}
