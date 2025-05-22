<?php

declare(strict_types=1);

namespace App\Tests\Domain\Entity;

use App\Entity\AwaitingPaymentFactory;
use PHPUnit\Framework\TestCase;

class AwaitingPaymentFactoryTest extends TestCase
{
    public function testCreateRandom4LetterString(): void
    {

        $code = AwaitingPaymentFactory::generateCode(4);

        $this->assertMatchesRegularExpression(
            '/^[A-Z0-9]{4}$/',
            $code,
            'Code should be 4 characters long and contain only uppercase letters and digits.'
        );
    }
}
