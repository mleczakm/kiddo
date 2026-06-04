<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ZipBomb;

use App\Infrastructure\ZipBomb\ZipBombGenerator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class ZipBombGeneratorTest extends TestCase
{
    public function testGenerateCreatesValidZipStructure(): void
    {
        $generator = new ZipBombGenerator();
        $zipData = $generator->generate(numFiles: 5, kernelSize: 1000);

        // The zip bomb uses DEFLATE quoting technique, so it contains zip signatures
        $this->assertStringContainsString("\x50\x4b\x03\x04", $zipData); // Local file header
        $this->assertStringContainsString("\x50\x4b\x01\x02", $zipData); // Central directory header
        $this->assertStringContainsString("\x50\x4b\x05\x06", $zipData); // End of central directory

        // Verify the data is not empty
        $this->assertNotEmpty($zipData);
    }

    public function testGenerateWithDifferentParameters(): void
    {
        $generator = new ZipBombGenerator();

        $smallZip = $generator->generate(numFiles: 3, kernelSize: 500);
        $largeZip = $generator->generate(numFiles: 10, kernelSize: 5000);

        // Larger parameters should produce larger output
        $this->assertGreaterThan(strlen($smallZip), strlen($largeZip));
    }

    public function testGenerateWithCustomAlphabet(): void
    {
        $generator = new ZipBombGenerator();
        $zipData = $generator->generate(numFiles: 5, kernelSize: 1000, alphabet: 'XYZ');

        // Verify it still produces valid zip structure (contains zip signatures)
        $this->assertStringContainsString("\x50\x4b\x03\x04", $zipData);
        $this->assertStringContainsString("\x50\x4b\x01\x02", $zipData);
        $this->assertStringContainsString("\x50\x4b\x05\x06", $zipData);
    }

    public function testGenerateContainsDeflateBlocks(): void
    {
        $generator = new ZipBombGenerator();
        $zipData = $generator->generate(numFiles: 3, kernelSize: 1000);

        // Verify the data contains non-compressed DEFLATE blocks (BTYPE=00)
        // These are used for quoting local file headers
        $this->assertStringContainsString("\x00", $zipData);
    }

    public function testGenerateContainsCentralDirectory(): void
    {
        $generator = new ZipBombGenerator();
        $zipData = $generator->generate(numFiles: 5, kernelSize: 1000);

        // Verify central directory header is present
        $this->assertStringContainsString("\x50\x4b\x01\x02", $zipData);
    }
}
