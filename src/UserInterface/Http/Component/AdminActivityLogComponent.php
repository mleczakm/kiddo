<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminActivityLogComponent
{
    use DefaultActionTrait;

    public function __construct() {}

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
