<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\ActivityLogger;
use App\Application\Service\InAppNotificationService;
use App\Entity\ActivityType;
use App\Entity\Child;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\ActivityLogRepository;
use App\Infrastructure\Doctrine\Repository\ChildRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Editable slice of the admin "user view" page: inline-editable contact
 * fields (admin note, name, phone), sending an in-app notification directly
 * from the user's own page, and adding/editing the user's children. Every
 * field change is recorded via ActivityLogger so it shows up both in the
 * global "Ostatnia aktywność" feed and in this component's own per-user
 * history list.
 */
#[AsLiveComponent]
final class AdminUserDetailComponent extends AbstractController
{
    use DefaultActionTrait;

    private const array EDITABLE_FIELDS = ['adminNote', 'name', 'phone'];

    #[LiveProp]
    public int $userId;

    #[LiveProp(writable: true)]
    public ?string $editingField = null;

    #[LiveProp(writable: true)]
    public string $fieldValue = '';

    #[LiveProp(writable: true)]
    public ?string $fieldError = null;

    #[LiveProp(writable: true)]
    public bool $composingNotification = false;

    #[LiveProp(writable: true)]
    public string $notifyTitle = '';

    #[LiveProp(writable: true)]
    public string $notifyBody = '';

    #[LiveProp(writable: true)]
    public bool $addingChild = false;

    #[LiveProp(writable: true)]
    public string $newChildName = '';

    #[LiveProp(writable: true)]
    public ?string $newChildBirthday = null;

    #[LiveProp(writable: true)]
    public ?string $editingChildId = null;

    #[LiveProp(writable: true)]
    public string $editChildName = '';

    #[LiveProp(writable: true)]
    public ?string $editChildBirthday = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ChildRepository $childRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InAppNotificationService $inAppNotifications,
        private readonly ActivityLogger $activityLogger,
        private readonly ActivityLogRepository $activityLogRepository,
    ) {}

    #[\Override]
    public function getUser(): User
    {
        return $this->userRepository->find($this->userId) ?? throw new \LogicException('User not found');
    }

    /**
     * @return Child[]
     */
    public function getChildren(): array
    {
        return $this->childRepository->findByOwner($this->getUser());
    }

    /**
     * @return list<array{title: string, summary: ?string, createdAt: \DateTimeImmutable}>
     */
    public function getHistory(): array
    {
        return array_values(array_map(static fn($log): array => [
            'title' => $log->getTitle(),
            'summary' => $log->getSummary(),
            'createdAt' => $log->getCreatedAt(),
        ], $this->activityLogRepository->findBySubject($this->getUser())));
    }

    #[LiveAction]
    public function startEditField(#[LiveArg] string $field): void
    {
        if (!in_array($field, self::EDITABLE_FIELDS, true)) {
            return;
        }

        $user = $this->getUser();
        $this->editingField = $field;
        $this->fieldError = null;
        $this->fieldValue = match ($field) {
            'adminNote' => $user->getAdminNote() ?? '',
            'name' => $user->getName(),
            'phone' => $user->getPhone()?->getNationalNumber() !== null
                ? '+' . $user->getPhone()->getCountryCode() . $user->getPhone()->getNationalNumber()
                : '',
        };
    }

    #[LiveAction]
    public function cancelEditField(): void
    {
        $this->editingField = null;
        $this->fieldValue = '';
        $this->fieldError = null;
    }

    #[LiveAction]
    public function saveField(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_USERS');

        $field = $this->editingField;
        if ($field === null || !in_array($field, self::EDITABLE_FIELDS, true)) {
            return;
        }

        $user = $this->getUser();
        $value = trim($this->fieldValue);
        $before = match ($field) {
            'adminNote' => $user->getAdminNote() ?? '',
            'name' => $user->getName(),
            'phone' => $user->getPhone()?->getNationalNumber() !== null
                ? '+' . $user->getPhone()->getCountryCode() . $user->getPhone()->getNationalNumber()
                : '',
        };

        if ($before === $value) {
            $this->cancelEditField();
            return;
        }

        $fieldLabel = match ($field) {
            'adminNote' => 'Notatkę administracyjną',
            'name' => 'Imię i nazwisko',
            'phone' => 'Telefon',
        };

        switch ($field) {
            case 'adminNote':
                $user->setAdminNote($value !== '' ? $value : null);
                break;
            case 'name':
                if ($value === '') {
                    $this->fieldError = 'Imię i nazwisko nie może być puste.';
                    return;
                }
                $user->setName($value);
                break;
            case 'phone':
                if ($value === '') {
                    $user->setPhone(null);
                    break;
                }
                try {
                    $user->setPhone(PhoneNumberUtil::getInstance()->parse($value, 'PL'));
                } catch (NumberParseException) {
                    $this->fieldError = 'Nieprawidłowy numer telefonu.';
                    return;
                }
                break;
        }

        $this->entityManager->flush();

        $this->activityLogger->log(
            type: ActivityType::USER_PROFILE_UPDATED,
            title: sprintf('Zaktualizowano dane użytkownika %s', $user->getName()),
            subject: $user,
            summary: sprintf(
                '%s: „%s” → „%s”',
                $fieldLabel,
                $before !== '' ? $before : '—',
                $value !== '' ? $value : '—',
            ),
        );

        $this->cancelEditField();
    }

    #[LiveAction]
    public function startCompose(): void
    {
        $this->composingNotification = true;
        $this->notifyTitle = '';
        $this->notifyBody = '';
    }

    #[LiveAction]
    public function cancelCompose(): void
    {
        $this->composingNotification = false;
        $this->notifyTitle = '';
        $this->notifyBody = '';
    }

    #[LiveAction]
    public function sendNotification(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_USERS');

        if (trim($this->notifyTitle) === '') {
            return;
        }

        $user = $this->getUser();
        $this->inAppNotifications->notify(
            $user,
            $this->notifyTitle,
            $this->notifyBody !== '' ? $this->notifyBody : null,
            null,
            NotificationSeverity::Info,
        );

        $this->addFlash('success', sprintf('Powiadomienie wysłane do %s.', $user->getName()));
        $this->cancelCompose();
    }

    #[LiveAction]
    public function startAddChild(): void
    {
        $this->addingChild = true;
        $this->newChildName = '';
        $this->newChildBirthday = null;
    }

    #[LiveAction]
    public function cancelAddChild(): void
    {
        $this->addingChild = false;
        $this->newChildName = '';
        $this->newChildBirthday = null;
    }

    #[LiveAction]
    public function saveNewChild(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_USERS');

        $name = trim($this->newChildName);
        if ($name === '') {
            return;
        }

        $birthday = $this->parseBirthday($this->newChildBirthday);
        $child = new Child($this->getUser(), $name, $birthday);
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        $this->activityLogger->log(
            type: ActivityType::USER_PROFILE_UPDATED,
            title: sprintf('Dodano dziecko %s', $child->getName()),
            subject: $this->getUser(),
            summary: sprintf('Dodano profil dziecka „%s”.', $child->getName()),
        );

        $this->cancelAddChild();
    }

    #[LiveAction]
    public function startEditChild(#[LiveArg] string $childId): void
    {
        $child = $this->findChild($childId);
        if ($child === null) {
            return;
        }

        $this->editingChildId = $childId;
        $this->editChildName = $child->getName();
        $this->editChildBirthday = $child->getBirthday()?->format('Y-m-d');
    }

    #[LiveAction]
    public function cancelEditChild(): void
    {
        $this->editingChildId = null;
        $this->editChildName = '';
        $this->editChildBirthday = null;
    }

    #[LiveAction]
    public function saveChild(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_USERS');

        $child = $this->editingChildId !== null ? $this->findChild($this->editingChildId) : null;
        $name = trim($this->editChildName);
        if ($child === null || $name === '') {
            return;
        }

        $before = $child->getName();
        $child->setName($name);
        $child->setBirthday($this->parseBirthday($this->editChildBirthday));
        $this->entityManager->flush();

        $this->activityLogger->log(
            type: ActivityType::USER_PROFILE_UPDATED,
            title: sprintf('Zaktualizowano dziecko %s', $child->getName()),
            subject: $this->getUser(),
            summary: sprintf('Profil dziecka: „%s” → „%s”', $before, $child->getName()),
        );

        $this->cancelEditChild();
    }

    private function findChild(string $childId): ?Child
    {
        try {
            $id = Ulid::fromString($childId);
        } catch (\Throwable) {
            return null;
        }

        foreach ($this->getChildren() as $child) {
            if ($child->getId()->equals($id)) {
                return $child;
            }
        }

        return null;
    }

    private function parseBirthday(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
