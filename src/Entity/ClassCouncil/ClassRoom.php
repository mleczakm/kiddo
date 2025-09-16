<?php

declare(strict_types=1);

namespace App\Entity\ClassCouncil;

use App\Entity\Tenant;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class ClassRoom
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /**
     * @var Collection<int, Student>
     */
    #[ORM\OneToMany(mappedBy: 'classRoom', targetEntity: Student::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $students;

    /**
     * @var Collection<int, ClassMembership>
     */
    #[ORM\OneToMany(mappedBy: 'classRoom', targetEntity: ClassMembership::class, cascade: [
        'persist',
    ], orphanRemoval: true)]
    private Collection $memberships;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Tenant::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Tenant $tenant,
        #[ORM\Column(length: 128)]
        private string $name
    ) {
        $this->id = new Ulid();
        $this->students = new ArrayCollection();
        $this->memberships = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    /**
     * @return Collection<int, ClassMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }
}
