<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Command\SendLoginNotification;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function form(Request $request, MessageBusInterface $messageBus): Response
    {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class)->add('submit', SubmitType::class, [
    'label' => 'form.login.submit',
])->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $messageBus->dispatch(new SendLoginNotification($data['email']));

            return $this->redirectToRoute('app_email_confirmation');
        }

        return $this->render('login.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/email-confirmation', name: 'app_email_confirmation')]
    public function mailConfirmation(): Response
    {
        return $this->render('email-confirmation.html.twig');
    }

    #[Route('/login_check', name: 'login_check')]
    public function check(): never
    {
        throw new LogicException('This code should never be reached');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new LogicException('This code should never be reached');
    }
}
