<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * "Subskrypcja kalendarza" card on the admin schedule page: shows the current
 * user their personal ICS feed URLs (all lessons / their own), a one-click
 * "add to Google Calendar" link, and a reset button that rotates the token.
 */
#[AsLiveComponent('StaffCalendarSubscription', template: 'components/StaffCalendarSubscriptionComponent.html.twig')]
final class StaffCalendarSubscriptionComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
    ) {}

    public function getToken(): ?string
    {
        return $this->currentUser()->getCalendarFeedToken();
    }

    /**
     * @return array<string, array{webcal: string, https: string, google: string}>
     *
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function getFeeds(): array
    {
        $token = $this->getToken();
        if ($token === null) {
            return [];
        }

        $feeds = [];
        foreach (['all' => 'wszystkie', 'mine' => 'moje'] as $key => $scope) {
            $https = $this->urlGenerator->generate(
                'staff_calendar_feed',
                [
                    'key' => $token,
                    'scope' => $scope,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $webcal = 'webcal://' . substr($https, (int) strpos($https, '://') + 3);
            $feeds[$key] = [
                'webcal' => $webcal,
                'https' => $https,
                'google' => 'https://calendar.google.com/calendar/r?cid=' . rawurlencode($webcal),
            ];
        }

        return $feeds;
    }

    /**
     * @throws \Random\RandomException
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    #[LiveAction]
    public function regenerate(): void
    {
        $this->denyAccessUnlessGranted('ROLE_HOST');

        $this->currentUser()->regenerateCalendarFeedToken();
        $this->entityManager->flush();
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();
        \assert($user instanceof User, 'The schedule page is behind ROLE_HOST.');

        return $user;
    }
}
