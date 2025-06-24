<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\Series;
use App\Entity\Transfer;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    #[\Override]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(LessonCrudController::class)->generateUrl());
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Kiddo Admin')
            ->setFaviconPath('favicon.ico')
            ->setTranslationDomain('admin');
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Content');
        yield MenuItem::linkToCrud('Series', 'fas fa-layer-group', Series::class);
        yield MenuItem::linkToCrud('Lessons', 'fas fa-calendar-alt', Lesson::class);

        yield MenuItem::section('Users & Bookings');
        yield MenuItem::linkToCrud('Users', 'fas fa-users', User::class);
        yield MenuItem::linkToCrud('Bookings', 'fas fa-ticket-alt', Booking::class);

        yield MenuItem::section('Financial');
        yield MenuItem::linkToCrud('Payments', 'fas fa-credit-card', Payment::class);
        yield MenuItem::linkToCrud('Transfers', 'fas fa-exchange-alt', Transfer::class);

        yield MenuItem::section('Settings');
        yield MenuItem::linkToRoute(t('admin_dashboard.frontpage'), 'fas fa-home', 'homepage');
        yield MenuItem::linkToLogout('Logout', 'fa fa-sign-out');
    }
}
