<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Lets an admin opt themselves out of (or back into) the operational email
 * audience; see {@see \App\Application\Service\BookingNotificationRecipients}.
 */
#[AsLiveComponent(
    'StaffNotificationPreferences',
    template: 'components/StaffNotificationPreferencesComponent.html.twig',
)]
final class StaffNotificationPreferencesComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {}

    public function isOperationalEmailsEnabled(): bool
    {
        return $this->currentUser()->receivesOperationalEmails();
    }

    /**
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    #[LiveAction]
    public function toggle(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->currentUser();
        $user->setOperationalEmails(!$user->receivesOperationalEmails());
        $this->entityManager->flush();
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();
        \assert($user instanceof User, 'The schedule page is behind ROLE_HOST.');

        return $user;
    }
}
