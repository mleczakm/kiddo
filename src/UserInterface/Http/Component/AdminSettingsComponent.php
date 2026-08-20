<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\FinanceContact;
use App\Entity\Setting;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\FinanceContactRepository;
use App\Infrastructure\Doctrine\Repository\SettingRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('AdminSettings', template: 'components/AdminSettingsComponent.html.twig')]
class AdminSettingsComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true)]
    public string $settingsTab = 'roles';

    #[LiveProp(writable: true)]
    public ?string $robotsTxtContent = null;

    #[LiveProp(writable: true)]
    public ?string $adminSearch = null;

    #[LiveProp(writable: true)]
    public ?string $hostSearch = null;

    #[LiveProp(writable: true)]
    public ?string $financeContactSearch = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly FinanceContactRepository $financeContactRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function mount(): void
    {
        // Only mount()-time state that's actually edited through a writable
        // LiveProp belongs here. Everything else (admin/host/finance-contact
        // lists) is queried fresh on every render by the getters below —
        // LiveComponents re-hydrate from a plain public property's *default*
        // value on every subsequent request/action (mount() only runs once,
        // on the very first render), so caching query results into plain
        // properties here would silently go stale after the first
        // interaction.
        $robotsSetting = $this->settingRepository->findOneBy([
            'key' => 'robots.txt',
        ]);
        $content = $robotsSetting?->getContent();
        $this->robotsTxtContent = is_array($content) && is_string($content['content'] ?? null)
            ? $content['content']
            : "User-agent: *\nAllow: /\nDisallow: /admin/";
    }

    /**
     * @return User[]
     */
    public function getAdminUsers(): array
    {
        return $this->userRepository->findByRole('ROLE_ADMIN');
    }

    /**
     * @return User[]
     */
    public function getHostUsers(): array
    {
        return $this->userRepository->findByRole('ROLE_HOST');
    }

    /**
     * @return User[]
     */
    public function getFinanceContactUsers(): array
    {
        return array_map(static fn(FinanceContact $fc) => $fc->getUser(), $this->financeContactRepository->findAll());
    }

    /**
     * Search results for the "add admin" picker, excluding users who are already admins.
     *
     * @return User[]
     */
    public function getFilteredUsersForAdmin(): array
    {
        return $this->searchExcluding($this->adminSearch, $this->getAdminUsers());
    }

    /**
     * Search results for the "add host" picker, excluding users who are already hosts.
     *
     * @return User[]
     */
    public function getFilteredUsersForHost(): array
    {
        return $this->searchExcluding($this->hostSearch, $this->getHostUsers());
    }

    /**
     * Search results for the "add finance contact" picker, excluding users who are already finance contacts.
     *
     * @return User[]
     */
    public function getFilteredUsersForFinanceContact(): array
    {
        return $this->searchExcluding($this->financeContactSearch, $this->getFinanceContactUsers());
    }

    /**
     * @param User[] $exclude
     * @return User[]
     */
    private function searchExcluding(?string $search, array $exclude): array
    {
        if ($search === null || mb_strlen(trim($search)) < 2) {
            return [];
        }

        $excludeIds = array_map(static fn(User $user) => (string) $user->getId(), $exclude);
        $results = $this->userRepository->findForAutocomplete($search);

        return array_values(array_filter(
            $results,
            static fn(User $user) => !in_array((string) $user->getId(), $excludeIds, true),
        ));
    }

    #[LiveAction]
    public function addFinanceContact(#[LiveArg] string $userId): void
    {
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            return;
        }

        $existing = $this->financeContactRepository->findOneBy([
            'user' => $user,
        ]);
        if ($existing !== null) {
            return;
        }

        $financeContact = new FinanceContact($user);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        $this->financeContactSearch = null;
    }

    #[LiveAction]
    public function removeFinanceContact(#[LiveArg] string $userId): void
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
    }

    #[LiveAction]
    public function addAdminUser(#[LiveArg] string $userId): void
    {
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            return;
        }

        if (!$user->hasRole('ROLE_ADMIN')) {
            $user->setRoles([...$user->getRoles(), 'ROLE_ADMIN']);
            $this->entityManager->flush();
        }

        $this->adminSearch = null;
    }

    #[LiveAction]
    public function removeAdminUser(#[LiveArg] string $userId): void
    {
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            return;
        }

        $roles = array_filter($user->getRoles(), static fn(string $role) => $role !== 'ROLE_ADMIN');
        $user->setRoles(array_values($roles));
        $this->entityManager->flush();
    }

    #[LiveAction]
    public function addHostUser(#[LiveArg] string $userId): void
    {
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            return;
        }

        if (!$user->hasRole('ROLE_HOST')) {
            $user->setRoles([...$user->getRoles(), 'ROLE_HOST']);
            $this->entityManager->flush();
        }

        $this->hostSearch = null;
    }

    #[LiveAction]
    public function removeHostUser(#[LiveArg] string $userId): void
    {
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            return;
        }

        $roles = array_filter($user->getRoles(), static fn(string $role) => $role !== 'ROLE_HOST');
        $user->setRoles(array_values($roles));
        $this->entityManager->flush();
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
}
