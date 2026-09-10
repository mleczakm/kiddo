<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Consent\ChildConsentManager;
use App\Entity\Child;
use App\Entity\ConsentSource;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\ChildRepository;
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
     * @var array<array{id: non-empty-string, name: string, birthday: non-empty-string|null, age: int|null}>
     */
    #[LiveProp]
    public array $children = [];

    #[LiveProp(writable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 120)]
    public string $childName = '';

    #[LiveProp(writable: true)]
    public ?string $childBirthday = null; // Y-m-d (optional)

    #[LiveProp(writable: true)]
    public bool $guardianConfirmed = false;

    public function __construct(
        private readonly ChildRepository $childRepository,
        private readonly EntityManagerInterface $em,
        private readonly ChildConsentManager $childConsentManager,
    ) {}

    public function mount(): void
    {
        $this->reload();
    }

    /** @throws \LogicException */
    public function isGuardianDeclarationRequired(): bool
    {
        $user = $this->getUser();

        return (
            $user instanceof User
            && $this->childConsentManager->isRequired()
            && !$this->childConsentManager->hasDeclaration($user)
        );
    }

    private function reload(): void
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->children = array_map(static fn(Child $c) => [
            'id' => (string) $c->getId(),
            'name' => $c->getName(),
            'birthday' => $c->getBirthday()?->format('Y-m-d'),
            'age' => $c->getAgeYears(),
        ], $this->childRepository->findByOwner($user));
    }

    #[LiveAction]
    public function addChild(): void
    {
        $this->validate();

        /** @var User $user */
        $user = $this->getUser();

        if ($this->isGuardianDeclarationRequired() && !$this->guardianConfirmed) {
            $this->addFlash('error', 'profile.children.guardian_required');
            return;
        }

        $birthday = null;
        if ($this->childBirthday) {
            $parsedBirthday = \DateTimeImmutable::createFromFormat('Y-m-d', $this->childBirthday);
            $birthday = $parsedBirthday === false ? null : $parsedBirthday;
            if ($birthday === null) {
                $this->addFlash('error', 'Invalid birthday format.');
                return;
            }
        }

        $child = new Child($user, $this->childName, $birthday);
        $this->em->persist($child);
        $this->em->flush();

        $this->childConsentManager->recordDeclaration($user, $child, ConsentSource::CHILD_FORM);

        $this->childName = '';
        $this->childBirthday = null;
        $this->guardianConfirmed = false;

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
            $this->em->flush();
            $this->addFlash('success', 'Child removed.');
        }
        $this->reload();
    }
}
