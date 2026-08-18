<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminActivityLogComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminActivityLogComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testEmptyStateWhenNoActivity(): void
    {
        $client = static::createClient();

        $component = $this->createLiveComponent(name: AdminActivityLogComponent::class, client: $client);

        /** @var AdminActivityLogComponent $activityLogComponent */
        $activityLogComponent = $component->component();
        static::assertSame([], $activityLogComponent->getActivityLog());
        static::assertStringContainsString('Brak ostatniej aktywności', (string) $component->render());
    }

    public function testShowsRecentActivityNewestFirst(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $customer = UserAssembler::new()->withName('Jan Kowalski')->assemble();
        $em->persist($customer);

        $em->persist(new ActivityLog(
            type: ActivityType::BOOKING_CREATED,
            title: 'Jan Kowalski zarezerwował/a zajęcia',
            subject: $customer,
            summary: 'Sensoplastyka',
            url: '/admin/uzytkownicy/1',
        ));
        $em->flush();

        // Ensure a strictly later createdAt so ordering is unambiguous regardless of clock resolution.
        $second = new ActivityLog(
            type: ActivityType::TRANSFER_UNMATCHED,
            title: 'Nierozpoznany przelew',
            summary: '150.00 PLN — Anna Nowak (wpłata)',
            url: '/admin/platnosci',
        );
        $em->persist($second);
        $em->flush();

        $component = $this->createLiveComponent(name: AdminActivityLogComponent::class, client: $client);
        /** @var AdminActivityLogComponent $activityLogComponent */
        $activityLogComponent = $component->component();
        $log = $activityLogComponent->getActivityLog();

        static::assertCount(2, $log);
        static::assertSame('transfer_unmatched', $log[0]['type']);
        static::assertSame('Nierozpoznany przelew', $log[0]['title']);
        static::assertNull($log[0]['name']);

        static::assertSame('booking_created', $log[1]['type']);
        static::assertSame('Jan Kowalski', $log[1]['name']);
        static::assertSame('Sensoplastyka', $log[1]['summary']);

        $html = (string) $component->render();
        static::assertStringContainsString('Jan Kowalski zarezerwował/a zajęcia', $html);
        static::assertStringContainsString('Nierozpoznany przelew', $html);
        static::assertStringContainsString('/admin/uzytkownicy/1', $html);
        // Polls for new events instead of sitting static
        static::assertStringContainsString('data-poll', $html);
    }
}
