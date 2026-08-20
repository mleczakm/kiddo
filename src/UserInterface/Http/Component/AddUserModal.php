<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\ActivityLogger;
use App\Entity\ActivityType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * "Dodaj użytkownika" modal — always rendered fully open, since UsersComponent
 * only mounts it while its own showAddModal flag is true and unmounts it
 * (stops rendering `{{ component('AddUserModal') }}`) on close/save.
 */
#[AsLiveComponent]
final class AddUserModal extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    /**
     * Roles an admin can grant explicitly. ROLE_USER is implicit for every
     * account and always included, so it isn't offered as a checkbox.
     *
     * @var array<string, string>
     */
    private const array ASSIGNABLE_ROLES = [
        'ROLE_HOST' => 'Instruktor',
        'ROLE_ADMIN' => 'Administrator',
        'ROLE_MANAGE_SCHEDULE' => 'Zarządzanie grafikiem',
        'ROLE_MANAGE_BOOKINGS' => 'Zarządzanie rezerwacjami',
        'ROLE_MANAGE_LESSONS' => 'Zarządzanie zajęciami',
        'ROLE_MANAGE_PAYMENTS' => 'Zarządzanie płatnościami',
        'ROLE_MANAGE_USERS' => 'Zarządzanie użytkownikami',
        'ROLE_SETTINGS' => 'Ustawienia',
        'ROLE_SUPER_ADMIN' => 'Super Administrator',
    ];

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public string $email = '';

    #[LiveProp(writable: true)]
    public string $phone = '';

    #[LiveProp(writable: true)]
    public string $adminNote = '';

    /**
     * @var array<int, string>
     */
    #[LiveProp(writable: true)]
    public array $roles = [];

    #[LiveProp(writable: true)]
    public bool $newsletterSubscribed = false;

    /**
     * @var array<string, string>
     */
    #[LiveProp]
    public array $errors = [];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function mount(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_USERS');
    }

    /**
     * @return array<string, string>
     */
    public function getAssignableRoles(): array
    {
        return self::ASSIGNABLE_ROLES;
    }

    #[LiveAction]
    public function toggleRole(#[LiveArg] string $role): void
    {
        if (!array_key_exists($role, self::ASSIGNABLE_ROLES)) {
            return;
        }

        if (in_array($role, $this->roles, true)) {
            $this->roles = array_values(array_filter($this->roles, static fn(string $r) => $r !== $role));
        } else {
            $this->roles[] = $role;
        }
    }

    #[LiveAction]
    public function close(): void
    {
        $this->emitUp('userModalClosed');
    }

    #[LiveAction]
    public function save(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_USERS');

        $this->errors = [];

        $name = trim($this->name);
        if ($name === '') {
            $this->errors['name'] = 'Imię i nazwisko jest wymagane.';
        }

        $email = mb_strtolower(trim($this->email));
        if ($email === '') {
            $this->errors['email'] = 'Adres e-mail jest wymagany.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Nieprawidłowy adres e-mail.';
        } elseif (
            $this->userRepository->findOneBy([
                'email' => $email,
            ]) !== null
        ) {
            $this->errors['email'] = 'Użytkownik z tym adresem e-mail już istnieje.';
        }

        $phone = null;
        $phoneInput = trim($this->phone);
        if ($phoneInput !== '') {
            try {
                $phone = PhoneNumberUtil::getInstance()->parse($phoneInput, 'PL');
            } catch (NumberParseException) {
                $this->errors['phone'] = 'Nieprawidłowy numer telefonu.';
            }
        }

        if ($this->errors !== []) {
            return;
        }

        $user = new User($email, $name);
        $user->setRoles(array_values(array_unique([...$this->roles, 'ROLE_USER'])));
        $user->setPhone($phone);

        $adminNote = trim($this->adminNote);
        $user->setAdminNote($adminNote !== '' ? $adminNote : null);

        $user->setNewsletterSubscribed($this->newsletterSubscribed);
        if ($this->newsletterSubscribed) {
            $user->setNewsletterConsentDate(Clock::get()->now());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->activityLogger->log(
            type: ActivityType::USER_REGISTERED,
            title: sprintf('Dodano użytkownika %s', $user->getName()),
            subject: $user,
            summary: sprintf('Konto założone ręcznie przez administratora (%s).', $user->getEmail()),
        );

        $this->addFlash('success', sprintf('Użytkownik %s został dodany.', $user->getName()));
        $this->emitUp('userModalSaved');
    }
}
