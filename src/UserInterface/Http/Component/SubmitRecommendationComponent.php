<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\RecommendationManager;
use App\Application\Service\RecommendationProvider;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class SubmitRecommendationComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $text = '';

    public ?string $error = null;

    public function __construct(
        private readonly RecommendationManager $manager,
        private readonly RecommendationProvider $provider,
    ) {}

    /** @return array{id: string, author: string, text: string, source: string, enabled: bool, status: string}|null
     * @throws \LogicException
     */
    public function getSubmission(): ?array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }
        foreach ($this->provider->getAll() as $row) {
            if ($row['id'] === 'user-' . $user->getId()) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @throws AccessDeniedException
     * @throws \LogicException
     */
    #[LiveAction]
    public function submit(): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $this->error = null;
        try {
            $this->manager->submit($user, $this->text);
            $this->text = '';
        } catch (\InvalidArgumentException $exception) {
            $this->error = $exception->getMessage();
        }
    }
}
