<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use PHPUnit\Framework\Attributes\Group;
use App\Infrastructure\Doctrine\EntityManagerResetter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class EntityManagerResetterTest extends TestCase
{
    public function testResetClosesEntityManager(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('close');

        $resetter = new EntityManagerResetter($em);
        $resetter->reset();
    }
}
