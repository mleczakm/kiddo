<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\MessageStatus;
use App\Entity\MessageType;
use App\Entity\Notification;
use App\Entity\UserMessage;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminMessagesComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class AdminMessagesComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    use InteractsWithMailer;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testSendReplyEmailsAndNotifiesTheUserInApp(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->assemble();
        $this->em->persist($customer);

        $userMessage = new UserMessage(
            $customer,
            'Pytanie o zajęcia',
            'Czy jest jeszcze miejsce na warsztaty w sobotę?',
            MessageType::GENERAL,
        );
        $this->em->persist($userMessage);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminMessagesComponent::class, client: $this->client);
        $component->set('selectedMessageId', (string) $userMessage->getId());
        $component->set('replyContent', 'Tak, mamy jeszcze 2 wolne miejsca!');
        $component->set('statusChange', MessageStatus::RESOLVED->value);
        $component->call('sendReply');

        $this->mailer()->assertSentEmailCount(1);
        $sentEmail = $this->mailer()->sentEmails()->first();
        self::assertSame($customer->getEmail(), $sentEmail->getTo()[0]->getAddress());
        self::assertStringContainsString(
            'Tak, mamy jeszcze 2 wolne miejsca!',
            (string) ($sentEmail->getHtmlBody() ?? $sentEmail->getTextBody())
        );

        $notifications = $this->em->getRepository(Notification::class)->findBy(['user' => $customer]);
        self::assertCount(1, $notifications);

        $this->em->clear();
        /** @var UserMessage $reloaded */
        $reloaded = $this->em->getRepository(UserMessage::class)->find($userMessage->getId());
        self::assertSame(MessageStatus::RESOLVED, $reloaded->getStatus());
        self::assertStringContainsString('Tak, mamy jeszcze 2 wolne miejsca!', (string) $reloaded->getAdminNotes());
    }
}
