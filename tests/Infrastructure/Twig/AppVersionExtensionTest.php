<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Twig;

use App\Infrastructure\Twig\AppVersionExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class AppVersionExtensionTest extends TestCase
{
    /**
     * @return array<string, list<string>>
     */
    public static function invalidDatetimeStringsProvider(): array
    {
        return [
            'not a date' => ['invalid'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validDatetimeProvider(): array
    {
        return [
            'valid datetime' => ['202401010001'],
        ];
    }

    #[DataProvider('invalidDatetimeStringsProvider')]
    public function testFailOnInvalidDatetimeFormat(string $invalidVersion): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AppVersionExtension($invalidVersion);
    }

    #[DataProvider('validDatetimeProvider')]
    public function testParseStringWithDatetime(string $datetime): void
    {
        $extension = new AppVersionExtension($datetime);
        $this->assertEquals(
            ($date = \DateTimeImmutable::createFromFormat('YmdHi', $datetime)) ? $date->format('YmdHi') : null,
            $extension->lastRelease()
                ->format('YmdHi')
        );
    }
}
