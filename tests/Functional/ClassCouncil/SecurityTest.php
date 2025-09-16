<?php

declare(strict_types=1);

namespace App\Tests\Functional\ClassCouncil;

use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\ClassRoom;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class SecurityTest extends WebTestCase
{
    private EntityManagerInterface $em {
        get {
            return $this->em ??= self::getContainer()->get(EntityManagerInterface::class);
        }
    }

    private string $host = 'classpay.test';

    private function ensureTenantAndClass(): ClassRoom
    {
        $tenant = new Tenant(name: 'ClassPay', domain: $this->host);
        $this->em->persist($tenant);

        $class = new ClassRoom($tenant, '1E');
        $this->em->persist($class);
        $this->em->flush();
        $this->em->clear();

        /** @var ClassRoom $reloaded */
        $reloaded = self::getContainer()->get('doctrine')->getRepository(ClassRoom::class)
            ->findOneBy([
                'tenant' => $tenant->getId(),
            ], [
                'name' => 'ASC',
            ]) ?? throw new \LogicException('Class not found');
        return $reloaded;
    }

    public function testDashboardRequiresLogin(): void
    {
        $client = static::createClient(server: [
            'HTTP_HOST' => $this->host,
        ]);

        $this->ensureTenantAndClass();

        $client->request('GET', '/');

        self::assertTrue(in_array($client->getResponse()->getStatusCode(), [302, 303], true), 'Should redirect');
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testStudentsRequiresLogin(): void
    {
        $this->markTestIncomplete();
        $client = static::createClient(server: [
            'HTTP_HOST' => $this->host,
        ]);

        $this->ensureTenantAndClass();


        $client->request('GET', '/students');

        self::assertTrue(in_array($client->getResponse()->getStatusCode(), [302, 303], true));
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTreasurerOnlyEndpointsForbiddenForNonTreasurer(): void
    {
        $this->markTestIncomplete();
        $client = static::createClient(server: [
            'HTTP_HOST' => $this->host,
        ]);

        $class = $this->ensureTenantAndClass();

        // Create regular user and membership as parent
        $user = new User('parent@example.com', 'Parent');
        $this->em->persist($user);
        $this->em->persist(new ClassMembership($user, $class, ClassRole::PARENT));
        $this->em->flush();


        $client->loginUser($user);

        // Treasurer overview
        $client->request('GET', '/cc/treasurer');
        self::assertSame(403, $client->getResponse()->getStatusCode(), 'Non-treasurer should get 403');

        // Expenses page
        $client->request('GET', '/cc/expenses');
        self::assertSame(403, $client->getResponse()->getStatusCode(), 'Non-treasurer should get 403');

        // Payment templates
        $client->request('GET', '/cc/payments/templates');
        self::assertSame(403, $client->getResponse()->getStatusCode(), 'Non-treasurer should get 403');
    }

    public function testTreasurerHasAccess(): void
    {
        $this->markTestIncomplete();
        $client = static::createClient(server: [
            'HTTP_HOST' => $this->host,
        ]);

        $class = $this->ensureTenantAndClass();

        // Create treasurer
        $treasurer = new User('treasurer@example.com', 'Treasurer');
        $this->em->persist($treasurer);
        $this->em->persist(new ClassMembership($treasurer, $class, ClassRole::TREASURER));
        $this->em->flush();


        $client->loginUser($treasurer);

        $client->request('GET', '/treasurer');
        self::assertSame(200, $client->getResponse()->getStatusCode(), 'Treasurer should access overview');

        $client->request('GET', '/expenses');
        self::assertSame(200, $client->getResponse()->getStatusCode(), 'Treasurer should access expenses');

        $client->request('GET', '/payments/templates');
        self::assertSame(200, $client->getResponse()->getStatusCode(), 'Treasurer should access templates');
    }
}
