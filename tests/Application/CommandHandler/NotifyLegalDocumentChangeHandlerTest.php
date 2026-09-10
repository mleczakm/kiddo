<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\NotifyLegalDocumentChange;
use App\Application\CommandHandler\NotifyLegalDocumentChangeHandler;
use App\Entity\File;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\NotificationRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class NotifyLegalDocumentChangeHandlerTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testEmailsAndNotifiesEveryAccountHolderExactlyOnce(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $reader = UserAssembler::new()->withEmail('reader@example.test')->withRoles('ROLE_USER')->assemble();
        $em->persist($reader);
        $version = $this->persistVersion($em);

        /** @var NotifyLegalDocumentChangeHandler $handler */
        $handler = $container->get(NotifyLegalDocumentChangeHandler::class);
        $handler(new NotifyLegalDocumentChange($version->getId()));
        $em->flush();

        $this->mailer()->assertSentEmailCount(1);
        $this->mailer()->assertEmailSentTo('reader@example.test', 'Zmiana dokumentu: Regulamin aplikacji');

        /** @var NotificationRepository $notifications */
        $notifications = $container->get(NotificationRepository::class);
        static::assertCount(1, $notifications->findBy(['user' => $reader]));
        static::assertNotNull($version->getNotifiedAt());

        // Second run is a no-op: notifiedAt already set.
        $handler(new NotifyLegalDocumentChange($version->getId()));
        $this->mailer()->assertSentEmailCount(1);
    }

    private function persistVersion(EntityManagerInterface $em): LegalDocumentVersion
    {
        $publisher = new User('legal-publisher@example.test', 'Publisher');
        $em->persist($publisher);

        $contents = 'app terms body';
        $file = new File(
            'app_terms.pdf',
            'application/pdf',
            \strlen($contents),
            hash('sha256', $contents),
            base64_encode($contents),
        );
        $document = new LegalDocument(LegalDocumentType::APP_TERMS);
        $version = new LegalDocumentVersion(
            $document,
            $file,
            new \DateTimeImmutable('-1 day'),
            $publisher,
            'Drobne poprawki.',
        );
        $em->persist($file);
        $em->persist($document);
        $em->persist($version);
        $em->flush();

        return $version;
    }
}
