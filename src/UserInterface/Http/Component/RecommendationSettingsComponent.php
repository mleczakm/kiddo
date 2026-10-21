<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\RecommendationProvider;
use App\Entity\Setting;
use App\Infrastructure\Doctrine\Repository\SettingRepository;
use App\UserInterface\Http\Component\Concern\ToastableComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
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

    /** @var list<array{author: string, text: string, source: string, enabled: bool}> */
    #[LiveProp(writable: true)]
    public array $reviews = [];

    public ?string $error = null;

    public function __construct(
        private readonly RecommendationProvider $recommendations,
        private readonly SettingRepository $settings,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @throws AccessDeniedException */
    public function mount(): void
    {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS');
        $this->reviews = $this->recommendations->getAll();
    }

    /** @throws AccessDeniedException */
    #[LiveAction]
    public function add(): void
    {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS');
        $this->reviews[] = ['author' => '', 'text' => '', 'source' => 'google', 'enabled' => true];
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
        } catch (\InvalidArgumentException $exception) {
            $this->error = $exception->getMessage();
            return;
        }

        $setting = $this->settings->findOneByKey(RecommendationProvider::SETTING_KEY);
        if ($setting === null) {
            $setting = new Setting();
            $setting->setKey(RecommendationProvider::SETTING_KEY);
            $this->entityManager->persist($setting);
        }

        $setting->setContent(['reviews' => $reviews]);
        $this->entityManager->flush();
        $this->reviews = $reviews;
        $this->toast('Opinie rodziców zostały zapisane.');
    }
}
