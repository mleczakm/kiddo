<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class LlmTxtControllerTest extends WebTestCase
{
    public function testLlmTxtEndpointReturnsValidContent(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=utf-8');
        self::assertTrue($client->getResponse()->headers->has('cache-control'));
    }

    public function testLlmTxtContainsMcpEndpoint(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('Model Context Protocol (MCP) Server', $content);
        self::assertStringContainsString('2026-07-28', $content);
        self::assertStringContainsString('HTTP', $content);
    }

    public function testLlmTxtListsUserTools(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        // Check for key user tools
        self::assertStringContainsString('user_me', $content);
        self::assertStringContainsString('user_list_upcoming_lessons', $content);
        self::assertStringContainsString('user_create_booking', $content);
        self::assertStringContainsString('user_list_children', $content);
    }

    public function testLlmTxtListsAdminTools(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        // Check for key admin tools
        self::assertStringContainsString('admin_today_schedule', $content);
        self::assertStringContainsString('admin_list_lessons', $content);
        self::assertStringContainsString('admin_create_booking', $content);
    }

    public function testLlmTxtContainsConfigurationExample(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('mcpServers', $content);
        self::assertStringContainsString('kiddo', $content);
        self::assertStringContainsString('X-Kiddo-Chat-Token', $content);
    }

    public function testLlmTxtContainsDomainKnowledge(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('Domain Knowledge', $content);
        self::assertStringContainsString('Lesson/Workshop', $content);
        self::assertStringContainsString('Booking', $content);
        self::assertStringContainsString('Carnet', $content);
    }

    public function testLlmTxtContainsBehaviorGuidelines(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('AI Agent Behavior Guidelines', $content);
        self::assertStringContainsString('confirm=true', $content);
    }
}
