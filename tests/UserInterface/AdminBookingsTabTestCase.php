<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AdminBookingsTabTestCase extends WebTestCase
{
    protected function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
