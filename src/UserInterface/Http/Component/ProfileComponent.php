<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;

#[AsLiveComponent]
class ProfileComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private \Symfony\Component\Security\Core\Security $security
    ) {}

    public function getUser(): ?User
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return null;
        }
        
        return $user;
    }
}
