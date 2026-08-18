<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\User;
use App\Repository\UserRepository;
use App\UserInterface\Http\Component\RegisterUser;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class RegisterUserTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testSubmittingWithNewsletterCheckedSubscribesUser(): void
    {
        $client = static::createClient();

        $testComponent = $this->createLiveComponent(name: RegisterUser::class, client: $client);
        $testComponent->submitForm([
            'form' => [
                'name' => 'Alice',
                'email' => 'brand.new@example.com',
                'newsletterSubscribed' => '1',
            ],
        ], action: 'save');

        $user = $this->findUserByEmail('brand.new@example.com');
        static::assertNotNull($user);
        static::assertTrue($user->isNewsletterSubscribed());
        static::assertNotNull($user->getNewsletterConsentDate());
    }

    public function testSubmittingWithoutNewsletterDoesNotSubscribe(): void
    {
        $client = static::createClient();

        $testComponent = $this->createLiveComponent(name: RegisterUser::class, client: $client);
        $testComponent->submitForm([
            'form' => [
                'name' => 'Bob',
                'email' => 'no.newsletter@example.com',
            ],
        ], action: 'save');

        $user = $this->findUserByEmail('no.newsletter@example.com');
        static::assertNotNull($user);
        static::assertFalse($user->isNewsletterSubscribed());
        static::assertNull($user->getNewsletterConsentDate());
    }

    private function findUserByEmail(string $email): ?User
    {
        $repository = self::getContainer()->get(UserRepository::class);

        return $repository->findOneBy([
            'email' => $email,
        ]);
    }
}
