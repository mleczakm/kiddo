<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Panel;

use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class AccountDataExportActionTest extends WebTestCase
{
    public function testReturnsTheUsersOwnDataAsAJsonDownload(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('exporter@example.test')->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/panel/konto/moje-dane.json');

        static::assertResponseIsSuccessful();
        static::assertResponseHeaderSame('Content-Type', 'application/json');
        static::assertStringContainsString(
            'attachment; filename="kiddo-moje-dane.json"',
            (string) $client->getResponse()->headers->get('Content-Disposition'),
        );

        /** @var array{profile: array{email: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('exporter@example.test', $payload['profile']['email']);
    }

    public function testGuestsCannotExport(): void
    {
        $client = static::createClient();
        $client->request('GET', '/panel/konto/moje-dane.json');

        static::assertResponseStatusCodeSame(302);
    }
}
