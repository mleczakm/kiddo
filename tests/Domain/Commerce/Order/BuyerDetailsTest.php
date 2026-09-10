<?php

declare(strict_types=1);

namespace App\Tests\Domain\Commerce\Order;

use App\Domain\Commerce\Order\BuyerDetails;
use App\Domain\Commerce\Order\BuyerType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BuyerDetailsTest extends TestCase
{
    public function testPrivateCustomerDoesNotStoreATaxIdentifier(): void
    {
        $details = BuyerDetails::fromInput('private', '8567346215');

        static::assertSame(BuyerType::PRIVATE, $details->type);
        static::assertNull($details->taxIdentifier);
    }

    public function testCompanyNormalizesAValidPolishNip(): void
    {
        $details = BuyerDetails::fromInput('company', '856-734-62-15');

        static::assertSame(BuyerType::COMPANY, $details->type);
        static::assertSame('8567346215', $details->taxIdentifier);
    }

    public function testCompanyRejectsAnInvalidNip(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BuyerDetails::company('1234567890');
    }
}
