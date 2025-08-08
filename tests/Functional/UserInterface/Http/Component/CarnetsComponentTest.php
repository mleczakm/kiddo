<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Component;

use App\UserInterface\Http\Component\CarnetsComponent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class CarnetsComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testCanRender(): void
    {
        $testComponent = $this->createLiveComponent(name: CarnetsComponent::class);

        $this->assertStringContainsString('div', (string) $testComponent->render());
    }
}
