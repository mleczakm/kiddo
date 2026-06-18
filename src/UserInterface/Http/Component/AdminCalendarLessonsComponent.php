<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminCalendarLessonsComponent
{
    use DefaultActionTrait;

    public function __construct() {}

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
}
