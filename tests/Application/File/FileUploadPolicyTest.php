<?php

declare(strict_types=1);

namespace App\Tests\Application\File;

use App\Application\File\FileUploadPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FileUploadPolicyTest extends TestCase
{
    public function testArticleImagePolicyRejectsNonImageMimeType(): void
    {
        $policy = new FileUploadPolicy('article_image');
        $this->expectException(InvalidArgumentException::class);
        $policy->assertValidFile('video/mp4', 1024, 0);
    }

    public function testArticleImagePolicyRejectsOversizedFile(): void
    {
        $policy = new FileUploadPolicy('article_image');
        $this->expectException(InvalidArgumentException::class);
        $policy->assertValidFile('image/jpeg', 10 * 1024 * 1024, 0);
    }

    public function testArticleImagePolicyRejectsExcessiveAggregate(): void
    {
        $policy = new FileUploadPolicy('article_image');
        $this->expectException(InvalidArgumentException::class);
        $policy->assertValidFile('image/jpeg', 1024, 51 * 1024 * 1024);
    }

    public function testArticleImagePolicyAcceptsValidJpeg(): void
    {
        $policy = new FileUploadPolicy('article_image');
        $policy->assertValidFile('image/jpeg', 5 * 1024 * 1024, 10 * 1024 * 1024);
        $this->addToAssertionCount(1);
    }

    public function testArticleImagePolicyAcceptsAllowedFormats(): void
    {
        $policy = new FileUploadPolicy('article_image');
        foreach (['image/jpeg', 'image/png', 'image/webp', 'image/avif'] as $mimeType) {
            $policy->assertValidFile($mimeType, 1024, 0);
        }
        $this->addToAssertionCount(4);
    }

    public function testArticleVideoPolicyRejectsImageMimeType(): void
    {
        $policy = new FileUploadPolicy('article_video');
        $this->expectException(InvalidArgumentException::class);
        $policy->assertValidFile('image/jpeg', 1024, 0);
    }

    public function testArticleVideoPolicyRejectsOversizedFile(): void
    {
        $policy = new FileUploadPolicy('article_video');
        $this->expectException(InvalidArgumentException::class);
        $policy->assertValidFile('video/mp4', 60 * 1024 * 1024, 0);
    }

    public function testArticleDocumentPolicyAcceptsPdfAndWordDocuments(): void
    {
        $policy = new FileUploadPolicy('article_document');
        $policy->assertValidFile('application/pdf', 10 * 1024 * 1024, 0);
        $policy->assertValidFile(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            5 * 1024 * 1024,
            0,
        );
        $this->addToAssertionCount(2);
    }

    public function testUnknownUsageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FileUploadPolicy('unknown_usage');
    }

    public function testCustomLimitsOverrideDefaults(): void
    {
        $policy = new FileUploadPolicy('article_image', fileSizeLimit: 2 * 1024 * 1024);
        $this->expectException(InvalidArgumentException::class);
        $policy->assertValidFile('image/jpeg', 3 * 1024 * 1024, 0);
    }
}
