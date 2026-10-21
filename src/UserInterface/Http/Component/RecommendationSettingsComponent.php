<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\RecommendationManager;
use App\Application\Service\RecommendationProvider;
use App\UserInterface\Http\Component\Concern\ToastableComponent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('RecommendationSettings', template: 'components/RecommendationSettingsComponent.html.twig')]
final class RecommendationSettingsComponent extends AbstractController
{
    use DefaultActionTrait;
    use ToastableComponent;

    /** @var list<array{id: string, author: string, text: string, source: string, enabled: bool, status: string}> */
    #[LiveProp(writable: true)]
    public array $reviews = [];

    #[LiveProp]
    public string $revision = '';

    public ?string $error = null;

    public function __construct(
        private readonly RecommendationProvider $recommendations,
        private readonly RecommendationManager $manager,
    ) {}

    /** @throws AccessDeniedException */
    public function mount(): void
    {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS');
        $this->reviews = $this->recommendations->getAll();
        $this->revision = RecommendationProvider::revisionOf($this->reviews);
    }

    /** @throws AccessDeniedException */
    #[LiveAction]
    public function add(): void
    {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS');
        $this->reviews[] = [
            'id' => (string) new Ulid(),
            'author' => '',
            'text' => '',
            'source' => 'google',
            'enabled' => true,
            'status' => 'published',
        ];
    }

    /** @throws AccessDeniedException */
    #[LiveAction]
    public function remove(#[LiveArg] int $index): void
    {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS');
        unset($this->reviews[$index]);
        $this->reviews = array_values($this->reviews);
    }

    /**
     * @throws AccessDeniedException
     * @throws \InvalidArgumentException
     */
    #[LiveAction]
    public function save(): void
    {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS');
        $this->error = null;

        try {
            $reviews = RecommendationProvider::validate($this->reviews);
            $this->manager->replace($reviews, $this->revision);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->error = $exception->getMessage();
            return;
        }

        $this->reviews = $reviews;
        $this->revision = RecommendationProvider::revisionOf($reviews);
        $this->toast('Opinie rodziców zostały zapisane.');
    }

    /**
     * @throws AccessDeniedException
     * @throws \InvalidArgumentException
     */
    #[LiveAction]
    public function approve(#[LiveArg] int $index): void
    {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS');
        if (($this->reviews[$index] ?? null) === null) {
            return;
        }
        $this->reviews[$index]['status'] = 'published';
        $this->reviews[$index]['enabled'] = true;
        $this->save();
    }
}
