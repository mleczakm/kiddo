<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Security\Voter;

use App\Application\Repository\FinanceContactRepositoryInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The monthly accounting report is for the people who do the books: finance
 * contacts and super admins. A plain ROLE_ADMIN is deliberately not enough.
 *
 * @extends Voter<string, null>
 */
final class AccountingReportVoter extends Voter
{
    public const string VIEW = 'ACCOUNTING_REPORT_VIEW';

    public function __construct(
        private readonly Security $security,
        private readonly FinanceContactRepositoryInterface $financeContacts,
    ) {}

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW;
    }

    #[\Override]
    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        #[\SensitiveParameter]
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        return $this->security->isGranted('ROLE_ADMIN') && $this->financeContacts->existsForUser($user);
    }
}
