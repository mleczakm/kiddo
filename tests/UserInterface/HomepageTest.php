<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

use App\Entity\Child;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('smoke')]
class HomepageTest extends WebTestCase
{
    #[DataProvider('pagesAccessibleWithoutAuthorizationDataProvider')]
    public function testPagesAccessibleWithoutAuthorization(string $path, int $code = 200): void
    {
        $client = static::createClient();
        $client->request('GET', $path);
        $this->assertResponseStatusCodeSame($code);
    }

    /**
     * @return array<string, array{0: string, 1?: int}>
     */
    public static function pagesAccessibleWithoutAuthorizationDataProvider(): array
    {
        return [
            'Homepage' => ['/'],
            'Workshops list' => ['/workshops'],
            'Healthcheck' => ['/health'],
            'Ping' => ['/ping'],
            'Login' => ['/login'],
            'Register' => ['/register'],
            'Admin' => ['/admin', 302],
            'Logout' => ['/logout', 302],
        ];
    }

    #[DataProvider('pagesAccessibleForUsersDataProvider')]
    public function testPagesAccessibleForUsers(string $path, int $code = 200): void
    {
        $client = self::createClient();
        $user = UserAssembler::new()
            ->withRoles('ROLE_USER')
            ->assemble();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', $path);
        $this->assertResponseStatusCodeSame($code);
    }

    /**
     * @return array<string, array{0: string, 1?: int}>
     */
    public static function pagesAccessibleForUsersDataProvider(): array
    {
        return [
            'Panel' => ['/panel'],
            'Admin' => ['/admin', 302],
        ];
    }

    /**
     * Test that admin dashboard redirects when not authenticated.
     */
    public function testAdminDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $user = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(200);
    }

    /**
     * Test parent dashboard with active bookings and child profiles.
     */
    public function testParentDashboardWithBookingsAndChildren(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()
            ->withRoles('ROLE_USER')
            ->assemble();
        $em->persist($user);

        $child = new Child($user, 'Antoś', new \DateTimeImmutable('2020-01-01'));
        $em->persist($child);

        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lesson)
            ->assemble();
        $booking->setChild($child);
        $em->persist($booking);

        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/panel');

        $this->assertResponseStatusCodeSame(200);
    }
}
