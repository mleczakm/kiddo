<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class UsersComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->userRepository->findAllMatching($this->query);
    }
}
