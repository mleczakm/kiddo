<?php

declare(strict_types=1);

namespace App\Tests\Application\Account;

use App\Application\Account\AccountDataExporter;
use App\Application\Command\RecordConsents;
use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Entity\Child;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('functional')]
final class AccountDataExporterTest extends KernelTestCase
{
    public function testExportCoversProfileChildrenAndConsentHistory(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = new User('exportme@example.test', 'Export Me');
        $em->persist($user);
        $em->persist(new Child($user, 'Lena', new \DateTimeImmutable('2020-03-03')));
        $em->flush();

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);
        $bus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::MARKETING_EMAIL, ConsentEvidence::statement('Zgoda marketingowa.')),
        ]));

        /** @var AccountDataExporter $exporter */
        $exporter = $container->get(AccountDataExporter::class);
        $json = json_encode($exporter->export($user), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        /** @var array{profile: array{email: string, name: string}, children: list<array{name: string}>, consents: list<array{type: string}>} $data */
        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame('exportme@example.test', $data['profile']['email']);
        static::assertSame('Export Me', $data['profile']['name']);
        static::assertContains('Lena', array_column($data['children'], 'name'));
        static::assertContains('marketing_email', array_column($data['consents'], 'type'));
        static::assertStringContainsString('"bookings"', $json);
        static::assertStringContainsString('"payments"', $json);
    }
}
