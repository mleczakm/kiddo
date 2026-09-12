<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use App\Entity\UserConsent;

/**
 * @extends RepositoryInterface<UserConsent>
 */
interface UserConsentRepositoryInterface extends RepositoryInterface
{
    public function findLatestActive(
        User $user,
        ConsentType $type,
        ?LegalDocumentType $documentType = null,
    ): ?UserConsent;

    /** @return list<UserConsent> */
    public function findActiveByType(User $user, ConsentType $type): array;

    /** @return list<UserConsent> */
    public function findHistoryForUser(User $user): array;
}
