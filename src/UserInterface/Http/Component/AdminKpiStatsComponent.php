<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminKpiStatsComponent
{
    use DefaultActionTrait;

    public function __construct() {}

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
}
