<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Payment;
use App\Entity\User;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UsersController extends AbstractController
{
    #[IsGranted('ROLE_MANAGE_USERS')]
    #[Route('/admin/uzytkownicy', name: 'app_admin_users')]
    public function index(): Response
    {
        return $this->render('admin/users/index.html.twig');
    }

    #[IsGranted('ROLE_MANAGE_USERS')]
    #[Route('/admin/uzytkownicy/{id}', name: 'app_admin_user_view', requirements: [
        'id' => '\d+',
    ])]
    public function view(User $user): Response
    {
        $totalSpent = Money::zero('PLN');
        foreach ($user->getBookings() as $booking) {
            $payment = $booking->getPayment();
            if ($payment !== null && $payment->getStatus() === Payment::STATUS_PAID) {
                $totalSpent = $totalSpent->plus($payment->getAmount());
            }
        }

        return $this->render('admin/users/view.html.twig', [
            'user' => $user,
            'totalSpent' => $totalSpent,
            'bookingsCount' => $user->getBookings()
                ->count(),
        ]);
    }
}
