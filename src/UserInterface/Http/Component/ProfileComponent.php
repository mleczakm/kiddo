<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Newsletter\NewsletterSubscriptionManager;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
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

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'profile.phone.required')]
    public string $phone = '';

    #[LiveProp]
    public ?string $phoneError = null;

    #[LiveProp(writable: true)]
    public bool $newsletterSubscribed = false;

    private User $user;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NewsletterSubscriptionManager $newsletterSubscriptionManager,
    ) {}

    #[LiveAction]
    public function startEditing(): void
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->name = $user->getName();
        $this->email = $user->getEmail();
        $this->phone = $user->getPhone() !== null
            ? PhoneNumberUtil::getInstance()->format($user->getPhone(), PhoneNumberFormat::NATIONAL)
            : '';
        $this->newsletterSubscribed = $user->isNewsletterSubscribed();
        $this->phoneError = null;
        $this->isEditing = true;
    }

    #[LiveAction]
    public function cancelEditing(): void
    {
        $this->isEditing = false;
        $this->phoneError = null;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->validate();
        $this->phoneError = null;

        /** @var User $user */
        $user = $this->getUser();
        $user->setName($this->name);
        $user->setEmail($this->email);

        $rawPhone = trim($this->phone);
        if ($rawPhone === '') {
            $this->phoneError = 'profile.phone.required';
            return;
        }

        try {
            $parsed = PhoneNumberUtil::getInstance()->parse($rawPhone, 'PL');
            if (!PhoneNumberUtil::getInstance()->isValidNumber($parsed)) {
                $this->phoneError = 'profile.phone.invalid';
                return;
            }
            $user->setPhone($parsed);
        } catch (NumberParseException) {
            $this->phoneError = 'profile.phone.invalid';
            return;
        }

        $this->newsletterSubscriptionManager->applyTransition(
            $user,
            $user->isNewsletterSubscribed(),
            $this->newsletterSubscribed,
        );

        $this->entityManager->flush();

        $this->addFlash('success', 'profile.update_success');
        $this->isEditing = false;

        $this->user = $user;
    }

    public function getFormattedPhone(): ?string
    {
        $phone = $this->getUser()->getPhone();
        if ($phone === null) {
            return null;
        }

        return PhoneNumberUtil::getInstance()->format($phone, PhoneNumberFormat::NATIONAL);
    }

    #[\Override]
    public function getUser(): User
    {
        return $this->user ??= ($user = parent::getUser()) && $user instanceof User
            ? $user
            : throw new \LogicException('Trying to load profile component on unauthenticated user.');
    }
}
