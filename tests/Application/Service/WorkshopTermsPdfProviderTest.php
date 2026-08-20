<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\File\FileStorageInterface;
use App\Application\Repository\FileRepositoryInterface;
use App\Application\Service\WorkshopTermsPdfProvider;
use App\Entity\File;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class WorkshopTermsPdfProviderTest extends TestCase
{
    private string $pdfPath;

    #[\Override]
    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'terms-pdf-');
        static::assertNotFalse($path);
        $this->pdfPath = $path;
        file_put_contents($this->pdfPath, '%PDF-1.4 fake terms');
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->pdfPath)) {
            unlink($this->pdfPath);
        }
    }

    public function testExistsIsTrueWhenThePdfFileIsPresent(): void
    {
        $provider = new WorkshopTermsPdfProvider(
            $this->createMock(FileRepositoryInterface::class),
            $this->createMock(FileStorageInterface::class),
            $this->pdfPath,
        );

        static::assertTrue($provider->exists());
    }

    public function testExistsIsFalseWhenThePdfFileIsMissing(): void
    {
        $provider = new WorkshopTermsPdfProvider(
            $this->createMock(FileRepositoryInterface::class),
            $this->createMock(FileStorageInterface::class),
            $this->pdfPath . '-missing',
        );

        static::assertFalse($provider->exists());
    }

    public function testFindOrStoreReusesAnExistingFileWithMatchingChecksum(): void
    {
        $existing = new File('Regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('x'));

        $fileRepository = $this->createMock(FileRepositoryInterface::class);
        $fileRepository->expects(static::once())->method('findOneBy')->willReturn($existing);
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $fileStorage->expects(static::never())->method('store');

        $provider = new WorkshopTermsPdfProvider($fileRepository, $fileStorage, $this->pdfPath);

        static::assertSame($existing, $provider->findOrStore());
    }

    public function testFindOrStoreStoresANewFileWhenNoneMatchesTheChecksum(): void
    {
        $stored = new File('Regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('x'));

        $fileRepository = $this->createMock(FileRepositoryInterface::class);
        $fileRepository->method('findOneBy')->willReturn(null);
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $fileStorage->expects(static::once())->method('store')->willReturn($stored);

        $provider = new WorkshopTermsPdfProvider($fileRepository, $fileStorage, $this->pdfPath);

        static::assertSame($stored, $provider->findOrStore());
    }
}
