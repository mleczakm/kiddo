<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatTokenManager;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ChatTokenManagerTest extends TestCase
{
    public function testMintAndParseRoundTrip(): void
    {
        $manager = new ChatTokenManager('test-secret');
        $user = UserAssembler::new()
            ->withId(42)
            ->withEmail('chat-token@example.com')
            ->withRoles('ROLE_USER')
            ->assemble();

        $token = $manager->mint($user, 600);
        $parsed = $manager->parse($token);

        self::assertSame(42, $parsed->userId);
        self::assertContains('ROLE_USER', $parsed->roles);
        self::assertFalse($parsed->isExpired());
        self::assertFalse($parsed->isGuest());
    }

    public function testMintAndParseGuestToken(): void
    {
        $manager = new ChatTokenManager('test-secret');
        $token = $manager->mintGuest(600);
        $parsed = $manager->parse($token);

        self::assertNull($parsed->userId);
        self::assertTrue($parsed->isGuest());
        self::assertSame([], $parsed->roles);
        self::assertFalse($parsed->isExpired());
    }

    public function testRejectsTamperedToken(): void
    {
        $manager = new ChatTokenManager('test-secret');
        $user = UserAssembler::new()
            ->withId(7)
            ->withEmail('chat-token2@example.com')
            ->withRoles('ROLE_USER')
            ->assemble();
        $token = $manager->mint($user);

        $this->expectException(\InvalidArgumentException::class);
        $manager->parse($token . 'x');
    }
}
