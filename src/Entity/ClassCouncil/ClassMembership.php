<?php

declare(strict_types=1);

namespace App\Entity\ClassCouncil;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_membership_user_class', fields: ['user', 'classRoom'])]
#[ORM\UniqueConstraint(name: 'uniq_treasurer_per_class', fields: ['classRoom', 'role'])]
class ClassMembership
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false)]
        private User $user,
        #[ORM\ManyToOne(targetEntity: ClassRoom::class, inversedBy: 'memberships')]
        #[ORM\JoinColumn(nullable: false)]
        private ClassRoom $classRoom,
        #[ORM\Column(type: 'string', enumType: ClassRole::class)]
        private ClassRole $role
    ) {
        $this->id = new Ulid();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getClassRoom(): ClassRoom
    {
        return $this->classRoom;
    }

    public function getRole(): ClassRole
    {
        return $this->role;
    }

    public function setRole(ClassRole $role): void
    {
        $this->role = $role;
    }
}
