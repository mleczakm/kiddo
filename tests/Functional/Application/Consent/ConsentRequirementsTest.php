<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Consent;

use App\Application\Consent\ConsentRequirements;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class ConsentRequirementsTest extends KernelTestCase
{
    public function testDoesNotRequireVersionedDocumentsWhileTheirDeliveryFlagIsDisabled(): void
    {
        self::bootKernel();
        $featureManager = $this->createMock(FeatureManager::class);
        $featureManager
            ->expects($this->exactly(2))
            ->method('isEnabled')
            ->willReturnMap([
                ['consents',        true],
                ['legal_documents', false],
            ]);
        self::getContainer()->set(FeatureManager::class, $featureManager);

        /** @var ConsentRequirements $requirements */
        $requirements = self::getContainer()->get(ConsentRequirements::class);

        static::assertFalse($requirements->isRegistrationAcceptanceRequired());
    }
}
