<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

#[AsLiveComponent]
class ProfileComponent extends AbstractController
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp]
    public bool $isEditing = false;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'profile.name.not_blank')]
    #[Assert\Length(min: 2, minMessage: 'profile.name.min_length')]
    public string $name = '';

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'profile.email.not_blank')]
    #[Assert\Email(message: 'profile.email.invalid')]
    public string $email = '';

    private User $user;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[LiveAction]
    public function startEditing(): void
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->name = $user->getName();
        $this->email = $user->getEmail();
        $this->isEditing = true;
    }

    #[LiveAction]
    public function cancelEditing(): void
    {
        $this->isEditing = false;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->validate();

        /** @var User $user */
        $user = $this->getUser();
        $user->setName($this->name);
        $user->setEmail($this->email);

        $this->entityManager->flush();

        $this->addFlash('success', 'profile.update_success');
        $this->isEditing = false;

        $this->user = $user;
    }

    public function getUser(): User
    {
        return $this->user ??= ($user = parent::getUser()) && $user instanceof User
            ? $user
            : throw new \LogicException('Trying to load profile component on unauthenticated user.');
    }
}
