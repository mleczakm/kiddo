<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\StaffCalendarSubscriptionComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class StaffCalendarSubscriptionComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface, 'test container provides the ORM entity manager');
        $this->em = $em;
    }

    public function testGeneratesAndRotatesTheFeedToken(): void
    {
        $host = UserAssembler::new()->withEmail('host@example.com')->withRoles('ROLE_HOST')->assemble();
        $this->em->persist($host);
        $this->em->flush();
        $this->client->loginUser($host);

        $component = $this->createLiveComponent(name: StaffCalendarSubscriptionComponent::class, client: $this->client);

        $before = (string) $component->render();
        static::assertStringContainsString('data-live-action-param="regenerate"', $before);
        static::assertStringNotContainsString('webcal://', $before);

        $html = (string) $component->call('regenerate')->render();
        static::assertStringContainsString('webcal://', $html);
        static::assertStringContainsString('/kalendarz/', $html);
        static::assertStringContainsString('wszystkie.ics', $html);
        static::assertStringContainsString('moje.ics', $html);
        static::assertStringContainsString('calendar.google.com/calendar/r?cid=', $html);

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($host->getId());
        static::assertNotNull($reloaded);
        $firstToken = $reloaded->getCalendarFeedToken();
        static::assertNotNull($firstToken);
        static::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $firstToken);

        $component->call('regenerate');
        $this->em->clear();
        $rotated = $this->em->getRepository(User::class)->find($host->getId());
        static::assertNotNull($rotated);
        static::assertNotSame($firstToken, $rotated->getCalendarFeedToken());
    }

    public function testRegenerateIsRefusedForNonStaff(): void
    {
        $customer = UserAssembler::new()->withEmail('customer@example.com')->assemble();
        $this->em->persist($customer);
        $this->em->flush();
        $this->client->loginUser($customer);

        $component = $this->createLiveComponent(name: StaffCalendarSubscriptionComponent::class, client: $this->client);

        $this->expectException(AccessDeniedException::class);
        $component->call('regenerate');
    }
}
