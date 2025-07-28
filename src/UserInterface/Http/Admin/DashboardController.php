<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\Series;
use App\Entity\Transfer;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

use function Symfony\Component\Translation\t;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[\Override]
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $em = $this->entityManager;
        // Fetch booking statuses summary
        $statuses = $em->createQuery(
            'SELECT b.status, COUNT(b.id) as count FROM App\\Entity\\Booking b GROUP BY b.status'
        )->getResult();

        // Fetch recent bookings (last 10)
        $recentBookings = $em->getRepository(\App\Entity\Booking::class)->findBy([], [
            'createdAt' => 'DESC',
        ], 10);

        // Fetch lessons for the next 10 days (including today)
        $today = new \DateTimeImmutable('today');
        $end = $today->modify('+9 days')
            ->setTime(23, 59, 59);
        $lessons = $em->createQuery(
            'SELECT l, b, u FROM App\\Entity\\Lesson l
             LEFT JOIN l.bookings b
             LEFT JOIN b.user u
             WHERE l.metadata.schedule >= :today AND l.metadata.schedule <= :end
                AND l.status = :status
             ORDER BY l.metadata.schedule ASC'
        )
            ->setParameter('today', $today)
            ->setParameter('end', $end)
            ->setParameter('status', 'active')
            ->getResult();

        return $this->render('admin/dashboard.html.twig', [
            'statuses' => $statuses,
            'recentBookings' => $recentBookings,
            'upcomingLessons' => $lessons,
        ]);
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
