<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Calendar;

use App\Application\Calendar\LessonCalendarFactory;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Personal, token-authenticated ICS subscription feed for staff (Google/Apple
 * "add calendar by URL"). Two scopes: every lesson, or only the ones the
 * subscriber instructs. No session — the unguessable key in the path is the
 * only credential, so it is also re-checked against the user's current roles.
 */
final class StaffCalendarFeedAction extends AbstractController
{
    private const string SCOPE_MINE = 'moje';

    /** @var array<string, string> */
    private const array CALENDAR_NAME = [
        'wszystkie' => 'Warsztatownia – wszystkie zajęcia',
        'moje' => 'Warsztatownia – moje zajęcia',
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly LessonCalendarFactory $calendarFactory,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {}

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \InvalidArgumentException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    #[Route(
        path: '/kalendarz/{key}/{scope}.ics',
        name: 'staff_calendar_feed',
        requirements: [
            'key' => '[a-f0-9]{32,64}',
            'scope' => 'wszystkie|moje',
        ],
        methods: ['GET'],
    )]
    public function __invoke(#[\SensitiveParameter] string $key, string $scope): Response
    {
        $user = $this->userRepository->findOneBy([
            'calendarFeedToken' => $key,
        ]);

        if (
            !$user instanceof User
            || !in_array('ROLE_HOST', $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true)
        ) {
            throw $this->createNotFoundException();
        }

        $now = Clock::get()->now();
        $start = $now->modify('-30 days');
        $end = $now->modify('+180 days');

        $lessons = $scope === self::SCOPE_MINE
            ? $this->lessonRepository->findUpcomingInRangeForInstructor($start, $end, true, $user)
            : $this->lessonRepository->findUpcomingInRange($start, $end, showCancelled: true);

        $body = $this->calendarFactory->calendarFeed($lessons, self::CALENDAR_NAME[$scope]);

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="' . $scope . '.ics"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
