<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Panel;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customer self-service panel sections. The "Overview" (/panel) stays on the
 * legacy `dashboard` route/name and the consolidated "My data" stays on
 * `profile`, so every existing path('dashboard') / path('profile') reference
 * and the BookingPreviewComponent deep-link keep working.
 */
#[IsGranted('ROLE_USER')]
final class PanelController extends AbstractController
{
    #[Route(path: [
        'en' => '/account/bookings',
        'pl' => '/panel/rezerwacje',
    ], name: 'panel_bookings')]
    public function bookings(): Response
    {
        return $this->render('panel/bookings.html.twig');
    }

    #[Route(path: [
        'en' => '/account/schedule',
        'pl' => '/panel/zajecia',
    ], name: 'panel_schedule')]
    public function schedule(): Response
    {
        return $this->render('panel/schedule.html.twig');
    }

    #[Route(path: [
        'en' => '/account/billing',
        'pl' => '/panel/rozliczenia',
    ], name: 'panel_billing')]
    public function billing(): Response
    {
        return $this->render('panel/billing.html.twig');
    }

    #[Route(path: [
        'en' => '/account/buy',
        'pl' => '/panel/kup',
    ], name: 'panel_buy')]
    public function buy(): Response
    {
        return $this->render('panel/buy.html.twig');
    }
}
