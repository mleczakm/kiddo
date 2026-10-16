<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Application\Accounting\ReportingMonth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    /**
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin', name: 'app_admin_dashboard')]
    public function index(Request $request): Response
    {
        $now = Clock::get()->now();
        $rawMonth = $request->query->get('miesiac');
        $monthStart = ReportingMonth::start(is_string($rawMonth) ? $rawMonth : null, $now);

        return $this->render('admin/dashboard/index.html.twig', [
            'month_start' => $monthStart,
            'month_key' => $monthStart->format('Y-m'),
            'month_label' => ReportingMonth::label($monthStart),
            'previous_month' => $monthStart->modify('-1 month')->format('Y-m'),
            'next_month' => $monthStart->modify('+1 month')->format('Y-m'),
            'is_current_month' => ReportingMonth::isCurrent($monthStart, $now),
        ]);
    }
}
