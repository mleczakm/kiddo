<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\EventSubscriber;

use App\Infrastructure\EventSubscriber\MaliciousRequestSubscriber;
use App\Infrastructure\ZipBomb\ZipBombGenerator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class MaliciousRequestSubscriberKernelTest extends KernelTestCase
{
    public function testSubscriberIsRegistered(): void
    {
        self::bootKernel();

        $subscriber = self::getContainer()->get(MaliciousRequestSubscriber::class);

        $this->assertInstanceOf(MaliciousRequestSubscriber::class, $subscriber);
    }

    public function testZipBombGeneratorIsRegistered(): void
    {
        self::bootKernel();

        $generator = self::getContainer()->get(ZipBombGenerator::class);

        $this->assertInstanceOf(ZipBombGenerator::class, $generator);
    }
}
