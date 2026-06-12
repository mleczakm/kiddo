<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImapEngine;

use App\Infrastructure\ImapEngine\AliorNotificationMailProvider;
use App\Infrastructure\Swoole\CurrentWorkerRestarterInterface;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
class AliorNotificationMailProviderTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testDoNotFailOnThrowable(): void
    {
        $testMailbox = new FakeMailbox(folders: [new ThrowingFolder('inbox')]);
        $workerRestarter = $this->createMock(CurrentWorkerRestarterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $provider = new AliorNotificationMailProvider($testMailbox, $workerRestarter, $logger);

        foreach ($provider() as $message) {
            $this->fail('No messages should be yielded when an exception occurs.');
        }
    }
}

class ThrowingFolder extends FakeFolder
{
    #[\Override]
    public function messages(): MessageQueryInterface
    {
        throw new \ErrorException('Simulated exception');
    }
}
