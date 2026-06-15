<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('smoke')]
class WorkshopsSmokeTest extends WebTestCase
{
    public function testWorkshopsPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/workshops');

        $this->assertResponseIsSuccessful();
    }

    public function testMobileBottomNavigationIsPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/workshops');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav.fixed.bottom-0');
        $this->assertSelectorExists('nav.fixed.bottom-0 a[href="/workshops"]');
    }
}
