<?php

namespace App\UserInterface\Http;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function form(Request $request, MailerInterface $mailer, UserProviderInterface $userProvider, LoginLinkHandlerInterface $loginLinkHandler): Response
    {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class)
            ->add('submit', SubmitType::class, [
                'label' => 'Login using link sent by email'
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var User $user */
            $user = $userProvider->loadUserByIdentifier($data['email']);

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user, lifetime: 60 * 60);
            $loginLink = $loginLinkDetails->getUrl();

            $email = (new Email())
                ->from('hello@example.com')
                ->to($user->getEmail())
                ->replyTo('no-reply@example.com')
                ->subject('Zaloguj się do Sensorycznej!')
                ->html(<<<HTML
<!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <style>
        body {
          font-family: 'Quicksand', Arial, sans-serif;
          line-height: 1.6;
          color: #4c2e11;
          max-width: 600px;
          margin: 0 auto;
          padding: 20px;
        }
        .email-container {
          background-color: #f9f1de;
          border-radius: 12px;
          padding: 30px;
          box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        .header {
          text-align: center;
          margin-bottom: 30px;
        }
        .logo {
          font-size: 28px;
          font-weight: bold;
        }
        .logo span {
          display: inline-block;
        }
        .red { color: #e34646; }
        .green { color: #98c93c; }
        .yellow { color: #f7c343; }
        .blue { color: #71c3db; }
        .pink { color: #eecbe9; }
        h1 {
          color: #4c2e11;
          margin-top: 0;
        }
        .button {
          display: inline-block;
          background-color: #e34646;
          color: white;
          text-decoration: none;
          padding: 12px 24px;
          border-radius: 6px;
          font-weight: 600;
          margin: 25px 0;
        }
        .button:hover {
          background-color: #d33636;
        }
        .note {
          background-color: rgba(247, 195, 67, 0.1);
          padding: 15px;
          border-left: 4px solid #f7c343;
          margin: 20px 0;
        }
        .footer {
          margin-top: 30px;
          text-align: center;
          font-size: 14px;
          color: #738f3e;
        }
        .help-text {
          font-size: 14px;
          color: #666;
          overflow-wrap: break-word;
        }
      </style>
    </head>
    <body>
      <div class="email-container">
        <div class="header">
          <div class="logo">
            <span class="red">S</span>
            <span class="green">e</span>
            <span class="yellow">n</span>
            <span class="blue">s</span>
            <span class="pink">o</span>
            <span class="red">r</span>
            <span class="green">y</span>
            <span class="yellow">c</span>
            <span class="blue">z</span>
            <span class="pink">n</span>
            <span class="red">a</span>
          </div>
        </div>
        
       <h1>Zaloguj się do swojego konta</h1>

<p>Witaj {$user->getName()},</p>

<p>Ostatnio poprosiłeś(-aś) o link do logowania do Sensorycznej. Kliknij poniższy przycisk, aby bezpiecznie zalogować się na swoje konto:</p>

<div style="text-align: center;">
  <a href="{$loginLink}" class="button">Zaloguj się</a>
</div>

<div class="note">
  <strong>Uwaga:</strong> Ten link do logowania wygaśnie za 15 minut ze względów bezpieczeństwa.
</div>

<p>Jeśli powyższy przycisk nie działa, możesz skopiować i wkleić poniższy link do swojej przeglądarki:</p>

<p class="help-text">{$loginLink}</p>

<p>Jeśli nie prosiłeś(-aś) o link do logowania, zignoruj tę wiadomość. Bezpieczeństwo Twojego konta jest dla nas ważne.</p>


        <div class="footer">
          <p>&copy; 2025 Sensoryczna. Wszystkie prawa zastrzeżone.</p>
        </div>
      </div>
    </body>
    </html>
HTML)
                ->text(<<<TEXT
Zaloguj się do swojego konta

Witaj {$user->getName()},

Ostatnio poprosiłeś(-aś) o link do logowania do Sensorycznej. Kliknij poniższy link, aby bezpiecznie zalogować się na swoje konto:

{$loginLink}

Uwaga: Ten link do logowania wygaśnie za 15 minut ze względów bezpieczeństwa.

Jeśli powyższy link nie działa, możesz skopiować i wkleić poniższy link do swojej przeglądarki:

{$loginLink}

Jeśli nie prosiłeś(-aś) o link do logowania, zignoruj tę wiadomość. Bezpieczeństwo Twojego konta jest dla nas ważne.

© 2025 Sensoryczna. Wszelkie prawa zastrzeżone.
TEXT);

            $mailer->send($email);

            return $this->redirectToRoute('app_email_confirmation');
        }

        return $this->render(
            'login.html.twig',
            [
                'form' => $form->createView()]
        );
    }

    #[Route('/email-confirmation', name: 'app_email_confirmation')]
    public function mailConfirmation(): Response
    {
        return $this->render('email-confirmation.html.twig',);
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