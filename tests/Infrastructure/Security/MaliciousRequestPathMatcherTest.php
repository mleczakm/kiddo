<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Infrastructure\Security\MaliciousRequestPathMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class MaliciousRequestPathMatcherTest extends TestCase
{
    private MaliciousRequestPathMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new MaliciousRequestPathMatcher();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function maliciousPathsProvider(): array
    {
        return [
            'env file' => ['/.env'],
            'php scanner wdone1' => ['/wdone1.php'],
            'php scanner root' => ['/root.php'],
            'php scanner a7' => ['/a7.php'],
            'php scanner with trailing slash' => ['/wdone1.php/'],
            'php scanner nested' => ['/admin/upload.php'],
            'wordpress admin' => ['/wp-admin'],
            'wordpress nested' => ['/wp-content/plugins/evil.php'],
            'sql dump' => ['/dump.sql'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function legitimatePathsProvider(): array
    {
        return [
            'homepage' => ['/'],
            'panel' => ['/panel'],
            'robots.txt' => ['/robots.txt'],
            'css asset' => ['/css/style.css'],
        ];
    }

    #[DataProvider('maliciousPathsProvider')]
    public function testMatchesMaliciousPaths(string $path): void
    {
        $this->assertTrue($this->matcher->matches($path));
    }

    #[DataProvider('legitimatePathsProvider')]
    public function testDoesNotMatchLegitimatePaths(string $path): void
    {
        $this->assertFalse($this->matcher->matches($path));
    }
}
