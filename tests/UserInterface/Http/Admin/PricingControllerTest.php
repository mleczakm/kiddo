<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Admin\PricingController;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Group('functional')]
final class PricingControllerTest extends WebTestCase
{
    public function testThrowsNotFoundWhenThePricingAdminFlagIsDisabled(): void
    {
        $disabled = $this->createMock(FeatureManager::class);
        $disabled->method('isEnabled')->with('pricing_admin')->willReturn(false);

        $controller = new PricingController($disabled);

        $this->expectException(NotFoundHttpException::class);
        $controller->index();
    }

    public function testDeniesAccessWithoutTheManagePricingRole(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $em->persist($host);
        $em->flush();
        $client->loginUser($host);

        $client->request('GET', '/admin/cennik');

        static::assertResponseStatusCodeSame(403);
    }

    public function testRendersForAnAdminWhenTheFlagIsEnabled(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $client->request('GET', '/admin/cennik');

        static::assertResponseIsSuccessful();
    }
}
