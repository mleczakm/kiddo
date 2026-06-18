<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminDashboardComponent
{
    use DefaultActionTrait;

    // This component is deprecated. Dashboard widgets have been extracted into separate components.
    // See: AdminKpiStatsComponent, AdminCalendarLessonsComponent, AdminActivityLogComponent
    // Routing has been moved to separate controllers in App\UserInterface\Http\Admin\*
}
