<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImapEngine;

use DirectoryTree\ImapEngine\MessageQueryInterface;
use DirectoryTree\ImapEngine\Testing\FakeFolder;

class ThrowingFolder extends FakeFolder
{
    #[\Override]
    public function messages(): MessageQueryInterface
    {
        throw new \ErrorException('Simulated exception');
    }
}
