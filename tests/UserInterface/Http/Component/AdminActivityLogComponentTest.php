<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\MessageType;
use App\Entity\UserMessage;
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

    public function testEmptyStateWhenNoCustomerActivity(): void
    {
        $client = static::createClient();

        $component = $this->createLiveComponent(name: AdminActivityLogComponent::class, client: $client);

        self::assertSame([], $component->component()->getActivityLog());
    }

    public function testShowsRecentCancellationAndRescheduleMessages(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $customer = UserAssembler::new()
            ->withName('Jan Kowalski')
            ->assemble();
        $em->persist($customer);

        $em->persist(new UserMessage(
            user: $customer,
            subject: 'Anulowano zajęcia: Sensoplastyka',
            message: 'Zajęcia "Sensoplastyka" zostały anulowane. Powód: choroba dziecka',
            type: MessageType::CANCELLATION_REQUEST,
        ));
        $em->persist(new UserMessage(
            user: $customer,
            subject: 'Zmieniono termin: Klub Malucha',
            message: 'Termin "Klub Malucha" zmieniono na "Klub Malucha - Środa".',
            type: MessageType::RESCHEDULE_REQUEST,
        ));
        $em->flush();

        $component = $this->createLiveComponent(name: AdminActivityLogComponent::class, client: $client);
        $log = $component->component()
            ->getActivityLog();

        self::assertCount(2, $log);
        self::assertSame('cancelled', $log[0]['type']);
        self::assertSame('Odwołane zajęcia', $log[0]['badge']);
        self::assertSame('Jan Kowalski', $log[0]['name']);
        self::assertSame('Anulowano zajęcia: Sensoplastyka', $log[0]['lesson']);

        self::assertSame('other', $log[1]['type']);
        self::assertSame('Zmieniono termin', $log[1]['badge']);

        $html = (string) $component->render();
        self::assertStringContainsString('Jan Kowalski', $html);
        self::assertStringContainsString('choroba dziecka', $html);
    }
}
