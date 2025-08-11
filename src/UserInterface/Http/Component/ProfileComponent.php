<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use App\Entity\User;

#[AsLiveComponent]
class ProfileComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct() {}

    public function getUser(): User
    {
        return parent::getUser();
    }
}
