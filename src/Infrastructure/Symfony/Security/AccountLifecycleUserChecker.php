<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Anonymised accounts can never authenticate again - the row only survives for
 * the non-nullable FKs on bookings/orders. A merely deactivated account (a
 * self-service pause, or a deletion whose grace period is still running) is
 * still allowed through: UserLoginSubscriber clears the flags on a successful
 * login, which is how "log back in to cancel" works.
 */
final class AccountLifecycleUserChecker implements UserCheckerInterface
{
    #[\Override]
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->getLifecycle()->isAnonymized()) {
            throw new CustomUserMessageAccountStatusException('account.status.anonymized');
        }
    }

    #[\Override]
    public function checkPostAuth(UserInterface $user, #[\SensitiveParameter] ?TokenInterface $token = null): void
    {
        // Anonymised accounts are already rejected in checkPreAuth.
        unset($user, $token);
    }
}
