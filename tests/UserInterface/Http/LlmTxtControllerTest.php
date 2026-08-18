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
        static::assertTrue($client->getResponse()->headers->has('cache-control'));
    }

    public function testLlmTxtContainsMcpEndpoint(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('Model Context Protocol (MCP) Server', $content);
        static::assertStringContainsString('2026-07-28', $content);
        static::assertStringContainsString('HTTP', $content);
    }

    public function testLlmTxtListsUserTools(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        static::assertIsString($content);

        // Check for key user tools
        static::assertStringContainsString('user_me', $content);
        static::assertStringContainsString('user_list_upcoming_lessons', $content);
        static::assertStringContainsString('user_create_booking', $content);
        static::assertStringContainsString('user_list_children', $content);
    }

    public function testLlmTxtListsAdminTools(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        static::assertIsString($content);

        // Check for key admin tools
        static::assertStringContainsString('admin_today_schedule', $content);
        static::assertStringContainsString('admin_list_lessons', $content);
        static::assertStringContainsString('admin_create_booking', $content);
    }

    public function testLlmTxtContainsConfigurationExample(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('mcpServers', $content);
        static::assertStringContainsString('kiddo', $content);
        static::assertStringContainsString('X-Kiddo-Chat-Token', $content);
    }

    public function testLlmTxtContainsDomainKnowledge(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('Domain Knowledge', $content);
        static::assertStringContainsString('Lesson/Workshop', $content);
        static::assertStringContainsString('Booking', $content);
        static::assertStringContainsString('Carnet', $content);
    }

    public function testLlmTxtContainsBehaviorGuidelines(): void
    {
        $client = static::createClient();

        $client->request('GET', '/llm.txt');

        $content = $client->getResponse()->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('AI Agent Behavior Guidelines', $content);
        static::assertStringContainsString('confirm=true', $content);
    }
}
