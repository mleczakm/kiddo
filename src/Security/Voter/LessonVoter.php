<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Lesson;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Object-level access to a specific Lesson. A ROLE_ADMIN always has access;
 * a ROLE_HOST only has access to lessons they're assigned to as an
 * instructor (directly, or via the lesson's Series) — see
 * Lesson::getAllInstructors(). Callers should turn a denial into a 404
 * (not a 403) so a host can't infer that a lesson id belonging to someone
 * else exists.
 *
 * @extends Voter<string, Lesson>
 */
final class LessonVoter extends Voter
{
    public const string VIEW = 'LESSON_VIEW';

    public const string MANAGE = 'LESSON_MANAGE';

    public function __construct(
        private readonly Security $security,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof Lesson;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (!$this->security->isGranted('ROLE_HOST')) {
            return false;
        }

        /** @var Lesson $lesson */
        $lesson = $subject;

        foreach ($lesson->getAllInstructors() as $instructor) {
            if ($instructor->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }
}
