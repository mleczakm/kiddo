<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\RecommendationChatTools;
use App\Application\Service\RecommendationManager;
use App\Application\Service\RecommendationProvider;
use App\Entity\Notification;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminGlobalSearchComponent;
use App\UserInterface\Http\Component\SubmitRecommendationComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class RecommendationModerationTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private RecommendationProvider $provider;
    private RecommendationManager $manager;
    private ChatToolRegistry $tools;

    #[\Override]
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var RecommendationProvider $provider */
        $provider = self::getContainer()->get(RecommendationProvider::class);
        $this->provider = $provider;
        /** @var RecommendationManager $manager */
        $manager = self::getContainer()->get(RecommendationManager::class);
        $this->manager = $manager;
        /** @var ChatToolRegistry $tools */
        $tools = self::getContainer()->get(ChatToolRegistry::class);
        $this->tools = $tools;
    }

    public function testSubmissionStaysPrivateNotifiesAdminsAndCanBeApprovedInEditor(): void
    {
        $admin = $this->user('ROLE_ADMIN');
        $host = $this->user('ROLE_HOST');
        $parent = $this->user('ROLE_USER');
        $this->client->loginUser($parent);
        $component = $this->createLiveComponent(SubmitRecommendationComponent::class, client: $this->client);
        $component->set('text', 'Moja autentyczna opinia <script>test</script>');
        $component->call('submit');
        static::assertStringContainsString('czeka na zatwierdzenie', (string) $component->render());
        $drafts = $this->provider->search('', 'draft');
        static::assertCount(1, $drafts);
        static::assertSame($parent->getName(), $drafts[0]['author']);
        static::assertSame('kiddo', $drafts[0]['source']);
        static::assertFalse($drafts[0]['enabled']);
        static::assertCount(20, $this->provider->getPublished());
        $notifications = $this->em->getRepository(Notification::class);
        static::assertCount(1, $notifications->findBy(['user' => $admin, 'title' => 'Nowa opinia do zatwierdzenia']));
        static::assertCount(0, $notifications->findBy(['user' => $host, 'title' => 'Nowa opinia do zatwierdzenia']));
        static::assertCount(0, $notifications->findBy(['user' => $parent, 'title' => 'Nowa opinia do zatwierdzenia']));
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        static::assertStringNotContainsString(
            'Moja autentyczna opinia',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($admin);
        $editor = $this->createLiveComponent('RecommendationSettings', client: $this->client);
        $editor->call('approve', ['index' => 20]);
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(21, '[data-recommendations-target="slide"]');
        static::assertStringContainsString(
            'Moja autentyczna opinia &lt;script&gt;',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testStaleEditorCannotEraseNewSubmissionAndDuplicateSubmitIsRejected(): void
    {
        $parent = $this->user('ROLE_USER');
        $reviews = $this->provider->getAll();
        $revision = RecommendationProvider::revisionOf($reviews);
        $this->manager->submit($parent, 'Nowa opinia w międzyczasie');
        try {
            $this->manager->replace($reviews, $revision);
            self::fail('An outdated editor must not overwrite a newer submission.');
        } catch (\RuntimeException $exception) {
            static::assertStringContainsString('Opinie zmieniły się', $exception->getMessage());
        }
        static::assertCount(21, $this->provider->getAll());
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->submit($parent, 'Duplikat');
    }

    public function testMcpSupportsDraftSearchApprovalHidingAndDeletionWithConfirmation(): void
    {
        $actor = new ChatActor($this->user('ROLE_ADMIN'), ['ROLE_ADMIN']);
        $arguments = ['author' => 'Rodzic MCP', 'text' => 'Opinia przez MCP', 'confirm' => true];
        $unconfirmed = $arguments;
        unset($unconfirmed['confirm']);
        static::assertFalse($this->tools->call('admin_save_recommendation', $actor, $unconfirmed)->ok);
        $saved = $this->tools->call('admin_save_recommendation', $actor, $arguments);
        static::assertTrue($saved->ok);
        $id = $saved->data['id'];
        $listed = $this->tools->call('admin_list_recommendations', $actor, ['query' => 'MCP', 'status' => 'draft']);
        static::assertSame(1, $listed->data['total']);
        static::assertCount(20, $this->provider->getPublished());
        static::assertFalse($this->tools->call('admin_approve_recommendation', $actor, ['id' => $id])->ok);
        static::assertTrue($this->tools->call('admin_approve_recommendation', $actor, [
            'id' => $id,
            'confirm' => true,
        ])->ok);
        static::assertCount(21, $this->provider->getPublished());
        static::assertTrue($this->tools->call('admin_save_recommendation', $actor, [
            ...$arguments,
            'id' => $id,
            'status' => 'published',
            'enabled' => false,
        ])->ok);
        static::assertCount(20, $this->provider->getPublished());
        static::assertTrue($this->tools->call('admin_delete_recommendation', $actor, [
            'id' => $id,
            'confirm' => true,
        ])->ok);
        static::assertCount(20, $this->provider->getAll());
    }

    public function testMcpNeverGrantsGuestsParentsOrInstructorsModerationAccess(): void
    {
        /** @var RecommendationChatTools $provider */
        $provider = self::getContainer()->get(RecommendationChatTools::class);
        foreach ([
            ChatActor::guest(),
            new ChatActor($this->user('ROLE_USER'), ['ROLE_USER']),
            new ChatActor($this->user('ROLE_HOST'), ['ROLE_HOST']),
        ] as $actor) {
            static::assertFalse($this->tools->call('admin_list_recommendations', $actor, [])->ok);
            static::assertFalse($provider->call('admin.approve_recommendation', $actor, [
                'id' => 'test',
                'confirm' => true,
            ])->ok);
            static::assertNotContains('admin.list_recommendations', array_column(
                $this->tools->definitions($actor),
                'name',
            ));
        }
    }

    public function testSearchFindsReviewsAndHelpOnlyForAdministrators(): void
    {
        $this->client->loginUser($this->user('ROLE_ADMIN'));
        $component = $this->createLiveComponent(AdminGlobalSearchComponent::class, client: $this->client);
        $component->set('query', 'Kinga Kaza');
        static::assertStringContainsString('tab=recommendations', (string) $component->render());
        static::assertStringContainsString('Kinga Kaza', (string) $component->render());
        $component->set('query', 'opinie');
        static::assertStringContainsString('/admin/pomoc/opinie-rodzicow', (string) $component->render());
        $this->client->request('GET', '/admin/pomoc/opinie-rodzicow');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'admin_approve_recommendation');
        $this->client->loginUser($this->user('ROLE_HOST'));
        $hostSearch = $this->createLiveComponent(AdminGlobalSearchComponent::class, client: $this->client);
        $hostSearch->set('query', 'Kinga Kaza');
        static::assertStringNotContainsString('tab=recommendations', (string) $hostSearch->render());
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/admin/pomoc/opinie-rodzicow');
        self::assertResponseStatusCodeSame(404);
    }

    public function testGuestCannotSubmitEvenByCallingLiveAction(): void
    {
        /** @var SubmitRecommendationComponent $component */
        $component = self::getContainer()->get(SubmitRecommendationComponent::class);
        $component->text = 'Nieautoryzowana opinia';
        $this->expectException(AccessDeniedException::class);
        $component->submit();
    }

    private function user(string $role): User
    {
        $user = UserAssembler::new()->withRoles($role)->assemble();
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
}
