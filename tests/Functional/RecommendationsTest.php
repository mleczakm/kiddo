<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Application\Service\RecommendationProvider;
use App\Entity\Setting;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\RecommendationSettingsComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class RecommendationsTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testMigrationImportsAllTwentyScreenshotReviews(): void
    {
        self::bootKernel();
        /** @var RecommendationProvider $provider */
        $provider = self::getContainer()->get(RecommendationProvider::class);
        $reviews = $provider->getAll();

        static::assertCount(20, $reviews);
        static::assertCount(14, array_filter($reviews, static fn(array $review) => $review['source'] === 'google'));
        static::assertCount(6, array_filter($reviews, static fn(array $review) => $review['source'] === 'facebook'));
        static::assertSame('Kinga Kaza', $reviews[0]['author']);
        static::assertStringEndsWith('Rodzice Rozalki', $reviews[0]['text']);
        static::assertSame('Iga Wolska', $reviews[19]['author']);
    }

    public function testAdminCanEditHideAddAndRemoveReviews(): void
    {
        $client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $settings = $this->createLiveComponent(name: 'AdminSettings', client: $client);
        $settings->set('settingsTab', 'recommendations');
        static::assertStringContainsString('Zapisz opinie', (string) $settings->render());

        $component = $this->createLiveComponent(name: 'RecommendationSettings', client: $client);
        $component->set('reviews', [
            [
                'author' => '  Prawdziwy rodzic  ',
                'text' => '<script>alert(1)</script> Świetne zajęcia!',
                'source' => 'facebook',
                'enabled' => true,
            ],
            ['author' => 'Ukryta opinia', 'text' => 'Niewidoczna treść', 'source' => 'google', 'enabled' => false],
        ]);
        $component->call('add');
        $component->call('remove', ['index' => 2]);
        $component->call('save');

        $em->clear();
        /** @var RecommendationProvider $provider */
        $provider = self::getContainer()->get(RecommendationProvider::class);
        $stored = $provider->getAll();
        static::assertCount(2, $stored);
        static::assertSame('Prawdziwy rodzic', $stored[0]['author']);
        static::assertFalse($stored[1]['enabled']);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#recommendations-title', 'Opinie rodziców');
        self::assertSelectorCount(1, '[data-recommendations-target="slide"]');
        self::assertSelectorTextContains('[data-recommendations-target="slide"]', 'Prawdziwy rodzic');
        self::assertSelectorTextContains('[data-recommendations-target="slide"]', 'Facebook');
        self::assertSelectorNotExists('[data-recommendations-target="controls"]');
        $html = (string) $client->getResponse()->getContent();
        static::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        static::assertStringNotContainsString('Niewidoczna treść', $html);

        $component = $this->createLiveComponent(name: 'RecommendationSettings', client: $client);
        $component->set('reviews', []);
        $component->call('save');
        $client->request('GET', '/');
        self::assertSelectorNotExists('#recommendations-title');
    }

    public function testInvalidEditsDoNotOverwriteStoredReviews(): void
    {
        $client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $component = $this->createLiveComponent(name: 'RecommendationSettings', client: $client);
        $component->set('reviews', [['author' => '', 'text' => '', 'source' => 'google', 'enabled' => true]]);
        $component->call('save');
        static::assertStringContainsString('Opinia 1:', (string) $component->render());
        /** @var RecommendationProvider $provider */
        $provider = self::getContainer()->get(RecommendationProvider::class);
        static::assertCount(20, $provider->getAll());
    }

    #[DataProvider('unauthorizedRoles')]
    public function testReviewEditorRejectsUsersWithoutSettingsPermission(string $role): void
    {
        $client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles($role)->assemble();
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $this->expectException(AccessDeniedException::class);
        $this->createLiveComponent(name: 'RecommendationSettings', client: $client)->render();
    }

    /** @return array<string, array{string}> */
    public static function unauthorizedRoles(): array
    {
        return ['parent' => ['ROLE_USER'], 'instructor' => ['ROLE_HOST']];
    }

    public function testSaveActionChecksPermissionEvenAfterMount(): void
    {
        self::bootKernel();
        /** @var RecommendationSettingsComponent $component */
        $component = self::getContainer()->get(RecommendationSettingsComponent::class);
        $component->reviews = [];

        $this->expectException(AccessDeniedException::class);
        $component->save();
    }

    public function testMissingSettingDoesNotShowPlaceholderQuotes(): void
    {
        $client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $setting = $em->getRepository(Setting::class)->findOneBy(['key' => RecommendationProvider::SETTING_KEY]);
        static::assertNotNull($setting);
        $em->remove($setting);
        $em->flush();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#recommendations-title');
    }
}
