<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\SendLoginNotification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsLiveComponent]
class RegisterUser extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    private ?User $user = null;

    private bool $isSubmitted = false;

    private bool $isSuccessful = false;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @return FormInterface<User>
     */
    protected function instantiateForm(): FormInterface
    {
        $this->user = new User();

        /** @var FormInterface<User> $form */
        $form = $this->createFormBuilder($this->user)
            ->add('name', TextType::class, [
                'label' => 'form.register.name',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],

            ])
            ->add('submit', SubmitType::class, [
                'label' => 'form.register.submit',
            ])
            ->getForm();

        return $form;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->submitForm();

        if ($this->getForm()->isValid()) {
            /** @var User $user */
            $user = $this->getForm()
                ->getData();
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->messageBus->dispatch(new SendLoginNotification($user->getEmail()));

            $this->isSuccessful = true;
            $this->isSubmitted = true;
        } else {
            $this->isSubmitted = true;
            $this->isSuccessful = false;
        }
    }

    public function isSubmitted(): bool
    {
        return $this->isSubmitted;
    }

    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }
}
