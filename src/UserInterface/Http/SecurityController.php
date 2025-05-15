<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function form(
        Request $request,
        NotifierInterface $notifier,
        UserProviderInterface $userProvider,
        LoginLinkHandlerInterface $loginLinkHandler,
        TranslatorInterface $translator
    ): Response {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class)
            ->add('submit', SubmitType::class, [
                'label' => 'Login using link sent by email',
            ])
            ->getForm();

        $form->handleRequest($request);

        if (true) {
            $data = [
                'email' => 'michal@mleczko.dev',
            ];
            $form->getData();

            /** @var User $user */
            $user = $userProvider->loadUserByIdentifier($data['email']);

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user, lifetime: 60 * 60);

            $translatorContext = [
                '%name%' => $user->getName(),
            ];

            $notification = new LoginLinkNotification(
                $loginLinkDetails,
                $translator->trans('login_link.subject', [], 'emails'),
            )->content($translator->trans('login_link.content.html', $translatorContext, 'emails'));

            $recipient = new Recipient($user->getEmail());

            $notifier->send($notification, $recipient);

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
        throw new \LogicException('This code should never be reached');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This code should never be reached');
    }
}
