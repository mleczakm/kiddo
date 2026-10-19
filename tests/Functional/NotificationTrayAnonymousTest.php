<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\UserInterface\Http\Component\NotificationTrayLiveComponent;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class NotificationTrayAnonymousTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    public function testRendersEmptyTrayWhenSessionIsGone(): void
    {
        $lc = $this->createLiveComponent(NotificationTrayLiveComponent::class, client: $this->client);

        $html = $lc->render()->toString();

        static::assertStringNotContainsString('notif-badge', $html);
        static::assertStringNotContainsString('app_logout', $html);
    }

    public function testActionsAreNoOpsWhenSessionIsGone(): void
    {
        $lc = $this->createLiveComponent(NotificationTrayLiveComponent::class, client: $this->client);

        $lc->call('clearAll');
        $lc->call('open', ['id' => '01JNONEXISTENT0000000000000']);

        static::assertStringNotContainsString('notif-badge', $lc->render()->toString());
    }

    #[\Override]
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }
}
