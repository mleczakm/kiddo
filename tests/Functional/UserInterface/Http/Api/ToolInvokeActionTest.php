<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Application\Chat\ChatActorResolver;
use App\Application\Chat\ChatTokenManager;
use App\Application\Chat\ChatToolRegistry;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Api\ToolInvokeAction;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('functional')]
final class ToolInvokeActionTest extends WebTestCase
{
    public function testListingAndInvokingToolsRequireAChatTokenOrASession(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/tools');

        self::assertResponseStatusCodeSame(401);
        static::assertStringContainsString('Missing Authorization', (string) $this->decode($client)['error']);

        $client->request('POST', '/api/v1/tools/user.list_upcoming_lessons', content: '{}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListingToolsRejectsAMalformedOrForgedToken(): void
    {
        $client = static::createClient();

        foreach (['not-a-token', 'Zm9v.forged-signature'] as $token) {
            $client->request('GET', '/api/v1/tools', server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]);

            self::assertResponseStatusCodeSame(401, $token);
        }
    }

    public function testListingToolsRejectsATokenForAUserThatNoLongerExists(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();
        /** @var ChatTokenManager $tokens */
        $tokens = static::getContainer()->get(ChatTokenManager::class);
        $token = $tokens->mint($user);
        $em->remove($user);
        $em->flush();

        $client->request('GET', '/api/v1/tools', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(401);
        static::assertSame('Chat token user not found', $this->decode($client)['error']);
    }

    public function testListingToolsForALoggedInAdminSessionExposesTheAdminCatalogWithItsMetadata(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $client->request('GET', '/api/v1/tools');

        self::assertResponseIsSuccessful();
        /** @var array{tools: list<array<string, mixed>>} $payload */
        $payload = $this->decode($client);
        /** @var array<string, array<string, mixed>> $byName */
        $byName = array_column($payload['tools'], null, 'name');
        static::assertArrayHasKey('admin.today_schedule', $byName);
        static::assertTrue($byName['admin.today_schedule']['requires_admin']);
        static::assertSame(
            ['name', 'description', 'input_schema', 'requires_admin', 'requires_confirm'],
            array_keys($byName['admin.today_schedule']),
        );
        static::assertContains(true, array_column($payload['tools'], 'requires_confirm'));
    }

    public function testInvokingAToolThatDoesNotExistFailsWithAnExplicitError(): void
    {
        $client = static::createClient();
        /** @var ChatTokenManager $tokens */
        $tokens = static::getContainer()->get(ChatTokenManager::class);

        $client->request(
            'POST',
            '/api/v1/tools/no.such_tool',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokens->mintGuest(),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{}',
        );

        self::assertResponseStatusCodeSame(422);
        $payload = $this->decode($client);
        static::assertFalse($payload['ok']);
        static::assertStringContainsString('Unknown tool: no.such_tool', (string) $payload['error']);
        static::assertSame([], $payload['data']);
    }

    public function testInvokingAToolWithAnInvalidJsonBodyIsABadRequest(): void
    {
        $client = static::createClient();
        /** @var ChatTokenManager $tokens */
        $tokens = static::getContainer()->get(ChatTokenManager::class);

        $client->request(
            'POST',
            '/api/v1/tools/user.list_upcoming_lessons',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokens->mintGuest(),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '"just a string"',
        );

        self::assertResponseStatusCodeSame(400);
        static::assertSame('Invalid JSON body', $this->decode($client)['error']);
    }

    public function testAnEmptyBodyIsTreatedAsNoArguments(): void
    {
        $client = static::createClient();
        /** @var ChatTokenManager $tokens */
        $tokens = static::getContainer()->get(ChatTokenManager::class);

        $client->request('POST', '/api/v1/tools/user.list_upcoming_lessons', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokens->mintGuest(),
        ]);

        self::assertResponseIsSuccessful();
        static::assertTrue($this->decode($client)['ok']);
    }

    public function testAdminToolsAreRefusedForARegularUser(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();
        /** @var ChatTokenManager $tokens */
        $tokens = static::getContainer()->get(ChatTokenManager::class);

        $client->request(
            'POST',
            '/api/v1/tools/admin.today_schedule',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokens->mint($user),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{}',
        );

        self::assertResponseStatusCodeSame(422);
        static::assertSame('This tool requires ROLE_ADMIN', $this->decode($client)['error']);
    }

    public function testBothEndpointsAreHiddenWhenTheChatAssistantFlagIsOff(): void
    {
        static::bootKernel();
        $container = static::getContainer();
        $disabled = $this->createMock(FeatureManager::class);
        $disabled->method('isEnabled')->with('chat_assistant')->willReturn(false);

        /** @var ChatToolRegistry $registry */
        $registry = $container->get(ChatToolRegistry::class);
        /** @var ChatActorResolver $actorResolver */
        $actorResolver = $container->get(ChatActorResolver::class);

        $action = new ToolInvokeAction($registry, $actorResolver, $disabled, new NullLogger());
        $action->setContainer($container);

        static::assertSame(404, $action->list(Request::create('/api/v1/tools'))->getStatusCode());
        static::assertSame(
            404,
            $action->invoke('user.list_upcoming_lessons', Request::create('/api/v1/tools/x', 'POST'))->getStatusCode(),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Expected a JSON object response.');
        }

        return $decoded;
    }
}
