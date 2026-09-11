<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Consent;

use App\Application\Consent\ConsentDispatcher;
use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('functional')]
final class ConsentDispatcherTest extends KernelTestCase
{
    public function testRecordPersistsThroughTheBus(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $user = new User('dispatcher@example.test', 'Dispatcher');
        $em->persist($user);
        $em->flush();

        /** @var ConsentDispatcher $dispatcher */
        $dispatcher = $container->get(ConsentDispatcher::class);
        $dispatcher->record(
            $user,
            ConsentSource::PROFILE,
            new ConsentGrant(ConsentType::MARKETING_EMAIL, ConsentEvidence::statement('Zgoda.')),
        );

        /** @var UserConsentRepository $repository */
        $repository = $container->get(UserConsentRepository::class);
        static::assertCount(1, $repository->findHistoryForUser($user));
    }

    public function testADispatchFailureIsSwallowedAndLoggedNotThrown(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new RuntimeException('bus down'));

        $dispatcher = new ConsentDispatcher($bus, new NullLogger());
        $user = new User('swallow@example.test', 'Swallow');

        // No exception escapes.
        $dispatcher->record(
            $user,
            ConsentSource::PROFILE,
            new ConsentGrant(ConsentType::AI_USAGE, ConsentEvidence::statement('x')),
        );
        $dispatcher->revoke($user, ConsentType::MARKETING_EMAIL, ConsentSource::PROFILE);

        static::expectNotToPerformAssertions();
    }
}
