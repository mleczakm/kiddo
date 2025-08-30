<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminDashboardComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $activeTab = 'dashboard';

    public function getTabUrl(string $tab): string
    {
        return match ($tab) {
            'dashboard' => '/admin',
            'lessons' => '/admin/zajecia',
            'schedule' => '/admin/harmonogram',
            'transfers' => '/admin/platnosci',
            'bookings' => '/admin/rezerwacje',
            'users' => '/admin/uzytkownicy',
            'messages' => '/admin/wiadomosci',
            default => '/admin',
        };
    }
}
