<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Application\Command\RecordConsents;
use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\PanelDocumentsComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class PanelDocumentsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testListsCurrentLegalDocumentLinks(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $rendered = $this
            ->createLiveComponent(name: PanelDocumentsComponent::class, client: $client)
            ->render()
            ->toString();

        static::assertStringContainsString('/regulamin', $rendered);
        static::assertStringContainsString('/regulamin-zajec', $rendered);
        static::assertStringContainsString('/polityka-prywatnosci', $rendered);
    }

    public function testRendersTheConsentHistoryOnceRowsExist(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        /** @var MessageBusInterface $commandBus */
        $commandBus = self::getContainer()->get(MessageBusInterface::class);
        $commandBus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
            new ConsentGrant(ConsentType::MARKETING_EMAIL, ConsentEvidence::statement('Zgoda marketingowa.')),
        ]));

        $rendered = $this
            ->createLiveComponent(name: PanelDocumentsComponent::class, client: $client)
            ->render()
            ->toString();

        static::assertStringContainsString('Historia zgód', $rendered);
        static::assertStringContainsString('Zgoda marketingowa (e-mail)', $rendered);
        static::assertStringContainsString('Obowiązuje', $rendered);
    }

    private function createUser(): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles('ROLE_USER')->assemble();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
