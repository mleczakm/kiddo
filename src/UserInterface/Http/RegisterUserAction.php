<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegisterUserAction extends AbstractController
{
    #[Route('/register', name: 'user_register')]
    public function form(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): Response {
        $form = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => 'Imię',
            ])
            ->add('email', EmailType::class)
            ->add('submit', SubmitType::class, [
                'label' => 'Register',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $user = new User();
            $user->setName($data['name']);
            $user->setEmail($data['email']);

            if ($validator->validate($user)->count() === 0) {
                $entityManager->persist($user);
                $entityManager->flush();
            }

            return $this->render('register.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        return $this->render('register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
