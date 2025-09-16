<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Twig;

use App\Entity\ClassCouncil\ClassRole;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Tenant\TenantContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ClassCouncilExtension extends AbstractExtension
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClassRoomRepository $classRooms,
        private readonly ClassMembershipRepository $memberships,
        private readonly Security $security,
    ) {}

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cc_is_treasurer', $this->isTreasurer(...)),
            new TwigFunction('cc_is_carer', $this->isCarer(...)),
        ];
    }

    public function isTreasurer(): bool
    {
        $user = $this->security->getUser();
        if (! $user) {
            return false;
        }

        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            return false;
        }

        $class = $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]);
        if (! $class) {
            return false;
        }

        $m = $this->memberships->findOneBy([
            'user' => $user,
            'classRoom' => $class,
        ]);
        return $m !== null && $m->getRole() === ClassRole::TREASURER;
    }

    public function isCarer(): bool
    {
        $user = $this->security->getUser();
        if (! $user) {
            return false;
        }

        $tenant = $this->tenantContext->getTenant();
        if (! $tenant) {
            return false;
        }

        $class = $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]);
        if (! $class) {
            return false;
        }

        $m = $this->memberships->findOneBy([
            'user' => $user,
            'classRoom' => $class,
        ]);
        if (! $m) {
            return false;
        }
        return in_array($m->getRole(), [ClassRole::TREASURER, ClassRole::PRESIDENT, ClassRole::VICE_PRESIDENT], true);
    }
}
