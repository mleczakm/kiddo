<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminDashboardComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $activeTab = 'dashboard';

    #[LiveAction]
    public function changeTab(#[LiveArg] string $tab): void
    {
        $this->activeTab = $tab;
    }
}
