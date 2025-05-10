<?php

declare(strict_types=1);

namespace Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomepageTest extends WebTestCase
{
    public function testHomepage(): void
    {
        $client = static::createClient([
            'environment' => 'test',
        ]);
        $crawler = $client->request('GET', '/');

        self::assertResponseStatusCodeSame(404);
    }
}
