<?php

declare(strict_types=1);

namespace App\Component;

use App\Entity\FinanceContact;
use App\Entity\Setting;
use App\Entity\User;
use App\Repository\FinanceContactRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('AdminSettings', template: 'components/AdminSettingsComponent.html.twig')]
class AdminSettingsComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $settingsTab = 'roles';

    public ?string $newFinanceContactUserId = null;

    public ?string $robotsTxtContent = null;

    public ?string $newAdminUserId = null;

    /**
     * @var array<int, string>
     */
    public array $adminUserIds = [];

    /**
     * @var array<int, string>
     */
    public array $financeContactUserIds = [];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly FinanceContactRepository $financeContactRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function mount(): void
    {
        $this->loadSettings();
    }

    private function loadSettings(): void
    {
        // Load admin users
        $adminUsers = $this->userRepository->findByRoles(['ROLE_ADMIN']);
        $this->adminUserIds = array_map(fn(User $user) => (string) $user->getId(), $adminUsers);

        // Load finance contacts
        $financeContacts = $this->financeContactRepository->findAll();
        $this->financeContactUserIds = array_map(
            fn(FinanceContact $fc) => (string) $fc->getUser()->getId(),
            $financeContacts
        );

        // Load robots.txt
        $robotsSetting = $this->settingRepository->findOneBy([
            'key' => 'robots.txt',
        ]);
        $this->robotsTxtContent = $robotsSetting?->getContent() ?? "User-agent: *\nAllow: /\nDisallow: /admin/";
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->userRepository->findAll();
    }

    /**
     * @return User[]
     */
    public function getAdminUsers(): array
    {
        if (empty($this->adminUserIds)) {
            return [];
        }

        return $this->userRepository->findBy([
            'id' => $this->adminUserIds,
        ]);
    }

    /**
     * @return User[]
     */
    public function getFinanceContactUsers(): array
    {
        if (empty($this->financeContactUserIds)) {
            return [];
        }

        return $this->userRepository->findBy([
            'id' => $this->financeContactUserIds,
        ]);
    }

    /**
     * @return User[]
     */
    public function getInstructorUsers(): array
    {
        return $this->userRepository->findByRoles(['ROLE_INSTRUCTOR']);
    }

    #[LiveAction]
    public function addFinanceContact(): void
    {
        if ($this->newFinanceContactUserId === null) {
            return;
        }

        $user = $this->userRepository->find((int) $this->newFinanceContactUserId);
        if ($user === null) {
            return;
        }

        // Check if already exists
        $existing = $this->financeContactRepository->findOneBy([
            'user' => $user,
        ]);
        if ($existing !== null) {
            return;
        }

        $financeContact = new FinanceContact($user);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        $this->newFinanceContactUserId = null;
        $this->loadSettings();
    }

    #[LiveAction]
    public function removeFinanceContact(string $userId): void
    {
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            return;
        }

        $financeContact = $this->financeContactRepository->findOneBy([
            'user' => $user,
        ]);
        if ($financeContact !== null) {
            $this->entityManager->remove($financeContact);
            $this->entityManager->flush();
        }

        $this->loadSettings();
    }

    #[LiveAction]
    public function addAdminUser(): void
    {
        if ($this->newAdminUserId === null) {
            return;
        }

        $user = $this->userRepository->find((int) $this->newAdminUserId);
        if ($user === null) {
            return;
        }

        $roles = $user->getRoles();
        if (! in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
            $user->setRoles($roles);
            $this->entityManager->flush();
        }

        $this->newAdminUserId = null;
        $this->loadSettings();
    }

    #[LiveAction]
    public function removeAdminUser(string $userId): void
    {
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            return;
        }

        $roles = $user->getRoles();
        $roles = array_filter($roles, fn(string $role) => $role !== 'ROLE_ADMIN');
        $user->setRoles(array_values($roles));
        $this->entityManager->flush();

        $this->loadSettings();
    }

    #[LiveAction]
    public function saveRobotsTxt(): void
    {
        $robotsSetting = $this->settingRepository->findOneBy([
            'key' => 'robots.txt',
        ]);

        if ($robotsSetting === null) {
            $robotsSetting = new Setting();
            $robotsSetting->setKey('robots.txt');
            $this->entityManager->persist($robotsSetting);
        }

        $robotsSetting->setContent([
            'content' => $this->robotsTxtContent,
        ]);
        $this->entityManager->flush();

        $this->addFlash('success', 'robots.txt has been saved successfully.');
    }

    public function hasRole(User $user, string $role): bool
    {
        return in_array($role, $user->getRoles(), true);
    }
}
