<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Notification;

use App\Application\Command\Notification\TransferRequiresReviewCommand;
use App\Application\CommandHandler\Notification\TransferRequiresReviewHandler;
use App\Entity\FinanceContact;
use App\Entity\Notification;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
class TransferRequiresReviewHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    private TransferRequiresReviewHandler $handler;

    private CacheItemPoolInterface $cache;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        /** @var TransferRequiresReviewHandler $handler */
        $handler = $container->get(TransferRequiresReviewHandler::class);
        $this->handler = $handler;
        /** @var CacheItemPoolInterface $cache */
        $cache = $container->get('cache.app');
        $this->cache = $cache;
        $this->cache->clear();
    }

    public function testSendsNotificationOnlyToFinanceContactsForALargeTransfer(): void
    {
        $admin = UserAssembler::new()
            ->withEmail('admin@example.com')
            ->withName('Admin One')
            ->withRoles('ROLE_ADMIN')
            ->assemble();
        $unrelatedAdmin = UserAssembler::new()
            ->withEmail('other-admin@example.com')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $transfer = TransferAssembler::new()
            ->withSender('Big Spender')
            ->withTitle('Large transfer')
            ->withAmount('1500.00')
            ->withTransferredAt($now = Clock::get()->now())
            ->assemble();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($admin);
        $em->persist($unrelatedAdmin);
        $em->persist(new FinanceContact($admin));
        $em->persist($transfer);
        $em->flush();

        ($this->handler)(new TransferRequiresReviewCommand($transfer));

        $this->assertEmailCount(1);
        $email = $this->mailer()->sentEmails()->first();
        $email->assertSubject('Duży przelew wymaga przeglądu');
        $email->assertContains('Big Spender');
        $email->assertContains('1500.00');
        $email->assertContains($now->format('Y-m-d H:i'));
        static::assertCount(1, $em->getRepository(Notification::class)->findBy(['user' => $admin]));
        static::assertCount(0, $em->getRepository(Notification::class)->findBy(['user' => $unrelatedAdmin]));
    }

    public function testDoesNotSendNotificationTwiceForTheSameTransfer(): void
    {
        $admin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $transfer = TransferAssembler::new()->withAmount('1500.00')->assemble();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($admin);
        $em->persist(new FinanceContact($admin));
        $em->persist($transfer);
        $em->flush();

        $command = new TransferRequiresReviewCommand($transfer);
        ($this->handler)($command);
        $this->mailer()->reset();
        ($this->handler)($command);

        static::assertCount(0, $this->mailer()->sentEmails()->all());
    }

    public function testDoesNotSendNotificationWhenNoFinanceContactsExist(): void
    {
        $transfer = TransferAssembler::new()->withAmount('1500.00')->assemble();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($transfer);
        $em->flush();

        ($this->handler)(new TransferRequiresReviewCommand($transfer));

        $this->assertEmailCount(0);
    }

    public function testRejectsTransferThatHasNotBeenPersisted(): void
    {
        $transfer = TransferAssembler::new()->assemble();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot notify about a transfer that has not been persisted.');

        ($this->handler)(new TransferRequiresReviewCommand($transfer));
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cache->clear();
    }
}
