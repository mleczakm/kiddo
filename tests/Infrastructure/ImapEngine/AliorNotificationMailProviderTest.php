<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImapEngine;

use DirectoryTree\ImapEngine\MessageQueryInterface;
use App\Infrastructure\ImapEngine\AliorNotificationMailProvider;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class AliorNotificationMailProviderTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testDoNotFailOnThrowable(): void
    {
        $testMailbox = new FakeMailbox(folders: [new ThrowingFolder('inbox')]);

        $provider = new AliorNotificationMailProvider($testMailbox);

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
