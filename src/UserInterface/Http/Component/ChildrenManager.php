<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Child;
use App\Entity\User;
use App\Repository\ChildRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

#[AsLiveComponent]
class ChildrenManager extends AbstractController
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    /**
     * @var array<array{id: non-empty-string, name: string, birthday: non-falsy-string|null, age: int|null}>
     */
    #[LiveProp]
    public array $children = [];

    #[LiveProp(writable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 120)]
    public string $childName = '';

    #[LiveProp(writable: true)]
    public ?string $childBirthday = null; // Y-m-d (optional)

    public function __construct(
        private readonly ChildRepository $childRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function mount(): void
    {
        $this->reload();
    }

    private function reload(): void
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->children = array_map(
            static fn(Child $c) => [
                'id' => (string)$c->getId(),
                'name' => $c->getName(),
                'birthday' => $c->getBirthday()?->format('Y-m-d'),
                'age' => $c->getAgeYears(),
            ],
            $this->childRepository->findByOwner($user)
        );
    }

    #[LiveAction]
    public function addChild(): void
    {
        $this->validate();

        /** @var User $user */
        $user = $this->getUser();

        $birthday = null;
        if ($this->childBirthday) {
            $birthday = \DateTimeImmutable::createFromFormat('Y-m-d', (string)$this->childBirthday) ?: null;
            if ($birthday === null) {
                $this->addFlash('error', 'Invalid birthday format.');
                return;
            }
        }

        $child = new Child($user, $this->childName, $birthday);
        $this->em->persist($child);
        $this->em->flush();

        $this->childName = '';
        $this->childBirthday = null;

        $this->reload();
        $this->addFlash('success', 'Child added.');
    }

    #[LiveAction]
    public function deleteChild(#[LiveArg] string $id): void
    {
        /** @var User $user */
        $user = $this->getUser();
        $child = $this->childRepository->find($id);
        if ($child instanceof Child && $child->getOwner()->getId() === $user->getId()) {
            $this->em->remove($child);
            $this->em->flush() ;
            $this->addFlash('success', 'Child removed.');
        }
        $this->reload();
    }
}
