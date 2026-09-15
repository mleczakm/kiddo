<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Application\Repository\PushSubscriptionRepositoryInterface;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class PushSubscriptionControllerTest extends WebTestCase
{
    public function testSubscribeCreatesASubscriptionForTheCurrentUser(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('push-subscribe@example.test')->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('POST', '/api/push/subscribe', content: json_encode([
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret'],
        ], JSON_THROW_ON_ERROR));

        static::assertResponseIsSuccessful();

        /** @var PushSubscriptionRepositoryInterface $subscriptions */
        $subscriptions = self::getContainer()->get(PushSubscriptionRepositoryInterface::class);
        $subscription = $subscriptions->findByEndpoint('https://push.example.test/abc');
        static::assertNotNull($subscription);
        static::assertSame($user->getId(), $subscription->getUser()->getId());
        static::assertSame('public-key', $subscription->getP256dh());
        static::assertSame('auth-secret', $subscription->getAuth());
    }

    public function testResubscribingTheSameEndpointUpdatesKeysInPlace(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('push-resubscribe@example.test')->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $body = static fn(string $auth): string => json_encode([
            'endpoint' => 'https://push.example.test/resub',
            'keys' => ['p256dh' => 'public-key', 'auth' => $auth],
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/push/subscribe', content: $body('first-secret'));
        static::assertResponseIsSuccessful();
        $client->request('POST', '/api/push/subscribe', content: $body('second-secret'));
        static::assertResponseIsSuccessful();

        /** @var PushSubscriptionRepositoryInterface $subscriptions */
        $subscriptions = self::getContainer()->get(PushSubscriptionRepositoryInterface::class);
        static::assertCount(1, $subscriptions->findByUser($user));
        static::assertSame(
            'second-secret',
            $subscriptions->findByEndpoint('https://push.example.test/resub')?->getAuth(),
        );
    }

    public function testUnsubscribeRemovesOnlyTheOwnersSubscription(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $owner = UserAssembler::new()->withEmail('push-owner@example.test')->withRoles('ROLE_USER')->assemble();
        $intruder = UserAssembler::new()->withEmail('push-intruder@example.test')->withRoles('ROLE_USER')->assemble();
        $em->persist($owner);
        $em->persist($intruder);
        $em->flush();

        $client->loginUser($owner);
        $client->request('POST', '/api/push/subscribe', content: json_encode([
            'endpoint' => 'https://push.example.test/owned',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret'],
        ], JSON_THROW_ON_ERROR));
        static::assertResponseIsSuccessful();

        // A different, logged-in user cannot unsubscribe someone else's endpoint.
        $client->loginUser($intruder);
        $client->request('POST', '/api/push/unsubscribe', content: json_encode([
            'endpoint' => 'https://push.example.test/owned',
        ], JSON_THROW_ON_ERROR));
        static::assertResponseIsSuccessful();

        /** @var PushSubscriptionRepositoryInterface $subscriptions */
        $subscriptions = self::getContainer()->get(PushSubscriptionRepositoryInterface::class);
        static::assertNotNull($subscriptions->findByEndpoint('https://push.example.test/owned'));

        $client->loginUser($owner);
        $client->request('POST', '/api/push/unsubscribe', content: json_encode([
            'endpoint' => 'https://push.example.test/owned',
        ], JSON_THROW_ON_ERROR));
        static::assertResponseIsSuccessful();
        static::assertNull($subscriptions->findByEndpoint('https://push.example.test/owned'));
    }

    public function testGuestsCannotSubscribe(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/push/subscribe', content: json_encode([
            'endpoint' => 'https://push.example.test/guest',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret'],
        ], JSON_THROW_ON_ERROR));

        static::assertResponseStatusCodeSame(401);
    }
}
