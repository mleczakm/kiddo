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

    #[LiveProp]
    public string $settingsTab = 'general';

    public function __construct() {}

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
            'settings' => '/admin/ustawienia',
            default => '/admin',
        };
    }

    /**
     * Expose KPI stats matching the mockup dashboard.
     * @return array<string, mixed>
     */
    public function getKpiStats(): array
    {
        return [
            'bookingsCount' => 142,
            'revenue' => '7 850 zł',
            'occupancyRate' => '78%',
        ];
    }

    /**
     * Expose upcoming calendar lessons matching the mockup dashboard.
     * @return array<int, array<string, string>>
     */
    public function getCalendarLessons(): array
    {
        return [
            [
                'time' => '10:00 - 11:30',
                'title' => 'Sensoplastyka dla maluchów',
                'instructor' => 'Marta W.',
                'age' => '0-3 lat',
                'capacity' => '8/10',
                'status' => 'Odbędzie się',
            ],
        ];
    }

    /**
     * Expose recent client activity logs matching the mockup dashboard.
     * @return array<int, array<string, string>>
     */
    public function getActivityLog(): array
    {
        return [
            [
                'type' => 'cancelled',
                'badge' => 'Odwołana obecność',
                'name' => 'Jaś Kowalski (2l)',
                'lesson' => 'Błotna Kuchnia (16.06, 16:30)',
                'notes' => 'Przez: Magdalena K. (Aplikacja) - zwrócono wejście na karnet.',
            ],
            [
                'type' => 'reserved',
                'badge' => 'Zapis na rezerwową',
                'name' => 'Antosia (3l)',
                'lesson' => 'Sensoplastyka (15.06)',
                'notes' => '',
            ],
        ];
    }
}
