<?php

declare(strict_types=1);

namespace App\Application\Help;

/**
 * Top-level areas of the built-in help centre. Each help article declares one
 * section; the index page groups articles by it in {@see self::order()}.
 */
enum DocSection: string
{
    case GettingStarted = 'getting-started';

    case Dashboard = 'dashboard';

    case Schedule = 'schedule';

    case Bookings = 'bookings';

    case Crm = 'crm';

    case Finance = 'finance';

    case Pricing = 'pricing';

    case Content = 'content';

    case Settings = 'settings';

    case Legal = 'legal';

    case CustomerPanel = 'customer-panel';

    case Ai = 'ai';

    case Newsletter = 'newsletter';

    case Api = 'api';

    case Calendar = 'calendar';

    public function label(): string
    {
        return match ($this) {
            self::GettingStarted => 'Pierwsze kroki',
            self::Dashboard => 'Pulpit',
            self::Schedule => 'Warsztaty i grafik',
            self::Bookings => 'Rezerwacje',
            self::Crm => 'Baza klientów (CRM)',
            self::Finance => 'Płatności i przelewy',
            self::Pricing => 'Cennik',
            self::Content => 'Treści i blog',
            self::Settings => 'Ustawienia systemu',
            self::Legal => 'Zgody i dokumenty prawne',
            self::CustomerPanel => 'Panel klienta',
            self::Ai => 'Asystent AI',
            self::Newsletter => 'Newsletter',
            self::Api => 'API i integracje',
            self::Calendar => 'Kalendarz',
        };
    }

    /** Lower number = higher on the index page. */
    public function order(): int
    {
        return match ($this) {
            self::GettingStarted => 0,
            self::Dashboard => 1,
            self::Schedule => 2,
            self::Bookings => 3,
            self::Crm => 4,
            self::Finance => 5,
            self::Pricing => 6,
            self::Content => 7,
            self::Settings => 8,
            self::Legal => 9,
            self::CustomerPanel => 10,
            self::Ai => 11,
            self::Newsletter => 12,
            self::Api => 13,
            self::Calendar => 14,
        };
    }
}
