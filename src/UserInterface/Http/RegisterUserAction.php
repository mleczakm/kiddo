<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Command\SendLoginNotification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterUserAction extends AbstractController
{
    #[Route('/register', name: 'user_register')]
    public function form(Request $request, EntityManagerInterface $entityManager, MessageBusInterface $messageBus): Response
    {
        $user = new User();
        $form = $this->createFormBuilder($user)->add('name', TextType::class, ['label' => 'form.register.name',])->add('email', EmailType::class)->add('submit', SubmitType::class, ['label' => 'form.register.submit',])->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();

            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
            $entityManager->flush();

            $messageBus->dispatch(new SendLoginNotification($user->getEmail()));

            return $this->redirectToRoute('app_email_confirmation');

        }

        return $this->render('register.html.twig', ['form' => $form->createView()]);
    }
}
