<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\ToolResult;
use App\Entity\Booking;
use App\Entity\User;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SettingAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

abstract class StaffChatToolsTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected ChatToolRegistry $registry;

    protected int $hostId;

    protected string $bobasyId;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        $registry = self::getContainer()->get(ChatToolRegistry::class);
        static::assertInstanceOf(ChatToolRegistry::class, $registry);
        $this->registry = $registry;

        $host = UserAssembler::new()->withName('Henryk Prowadzący')->withRoles('ROLE_HOST')->assemble();
        $p1 = UserAssembler::new()->withName('Anna Kowalska')->withEmail('anna@example.com')->assemble();
        $p2 = UserAssembler::new()->withName('Bartek Nowak')->withEmail('bartek@example.com')->assemble();
        $p3 = UserAssembler::new()->withName('Cezary Wójcik')->withEmail('cezary@example.com')->assemble();

        $bobasy = LessonAssembler::new()
            ->withTitle('Senso bobasy')
            ->withSchedule(Clock::get()->now()->modify('+2 days'))
            ->withCapacity(10)
            ->assemble();
        $rytmika = LessonAssembler::new()
            ->withTitle('Rytmika dla smyka')
            ->withSchedule(Clock::get()->now()->modify('+3 days'))
            ->withCapacity(10)
            ->assemble();
        $rytmika->addInstructor($host);

        $active1 = BookingAssembler::new()
            ->withUser($p1)
            ->withPayment(null)
            ->withLessons($bobasy)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $active2 = BookingAssembler::new()
            ->withUser($p2)
            ->withPayment(null)
            ->withLessons($bobasy)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $cancelled = BookingAssembler::new()
            ->withUser($p3)
            ->withPayment(null)
            ->withLessons($bobasy)
            ->withStatus(Booking::STATUS_CANCELLED)
            ->assemble();
        foreach ([$active1, $active2, $cancelled] as $booking) {
            $bobasy->addBooking($booking);
        }

        $organizationDetails = SettingAssembler::new()->asOrganizationDetails()->assemble();

        foreach ([
            $host,
            $p1,
            $p2,
            $p3,
            $bobasy,
            $rytmika,
            $active1,
            $active2,
            $cancelled,
            $organizationDetails,
        ] as $e) {
            $this->em->persist($e);
        }
        $this->em->flush();

        $this->hostId = (int) $host->getId();
        $this->bobasyId = (string) $bobasy->getId();
        $this->em->clear();
    }

    protected function hostActor(): ChatActor
    {
        $host = $this->em->find(User::class, $this->hostId);
        static::assertInstanceOf(User::class, $host);

        return new ChatActor($host, ['ROLE_HOST']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rows(ToolResult $result, string $key): array
    {
        static::assertArrayHasKey($key, $result->data);
        static::assertIsArray($result->data[$key]);

        /** @var list<array<string, mixed>> */
        return $result->data[$key];
    }

    protected function intData(ToolResult $result, string $key): int
    {
        static::assertArrayHasKey($key, $result->data);
        static::assertIsInt($result->data[$key]);

        return $result->data[$key];
    }
}
