<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\UserInterface\Http\Component\CarnetsComponent;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class CarnetsComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testCanRender(): void
    {
        $testComponent = $this->createLiveComponent(name: CarnetsComponent::class);

        $this->assertStringContainsString('div', (string) $testComponent->render());
    }
}
