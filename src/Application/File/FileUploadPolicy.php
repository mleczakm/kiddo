<?php

declare(strict_types=1);

namespace App\Application\File;

use InvalidArgumentException;

/**
 * Defines upload constraints by usage context:
 * article_image: pictures in cover/inline/attachments (JPEG, PNG, WebP, AVIF)
 * article_video: playable media with Range support (MP4, WebM, Ogg)
 * article_document: downloadable files (PDF, DOCX, XLSX, PPTX)
 */
final readonly class FileUploadPolicy
{
    /** Size limit in bytes per file */
    private int $fileSizeLimit;
    /** Maximum number of files per article */
    private int $maxFileCount;
    /** Maximum decoded bytes across all files per article */
    private int $aggregateSizeLimit;

    /** @var list<string> */
    private array $allowedMimeTypes;

    /** @throws InvalidArgumentException */
    public function __construct(
        string $usage,
        ?int $fileSizeLimit = null,
        ?int $maxFileCount = null,
        ?int $aggregateSizeLimit = null,
    ) {
        $this->fileSizeLimit = $fileSizeLimit ?? match ($usage) {
            'article_image' => 8 * 1024 * 1024, // 8 MB per image
            'article_video' => 50 * 1024 * 1024, // 50 MB per video
            'article_document' => 20 * 1024 * 1024, // 20 MB per document
            default => throw new InvalidArgumentException("Unknown usage: {$usage}"),
        };

        $this->maxFileCount = $maxFileCount ?? match ($usage) {
            'article_image' => 10,
            'article_video' => 5,
            'article_document' => 10,
            default => throw new InvalidArgumentException("Unknown usage: {$usage}"),
        };

        $this->aggregateSizeLimit = $aggregateSizeLimit ?? match ($usage) {
            'article_image' => 50 * 1024 * 1024, // 50 MB total images
            'article_video' => 100 * 1024 * 1024, // 100 MB total videos
            'article_document' => 100 * 1024 * 1024, // 100 MB total documents
            default => throw new InvalidArgumentException("Unknown usage: {$usage}"),
        };

        $this->allowedMimeTypes = match ($usage) {
            'article_image' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
            'article_video' => ['video/mp4', 'video/webm', 'video/ogg'],
            'article_document' => [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
            default => throw new InvalidArgumentException("Unknown usage: {$usage}"),
        };
    }

    /** @throws InvalidArgumentException */
    public function assertValidFile(string $mimeType, int $decodedSize, int $totalUploadedSize): void
    {
        if (!\in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new InvalidArgumentException(\sprintf('MIME type "%s" is not allowed for this upload.', $mimeType));
        }

        if ($decodedSize > $this->fileSizeLimit) {
            throw new InvalidArgumentException(\sprintf(
                'File size %d bytes exceeds limit of %d bytes.',
                $decodedSize,
                $this->fileSizeLimit,
            ));
        }

        if (($totalUploadedSize + $decodedSize) > $this->aggregateSizeLimit) {
            throw new InvalidArgumentException(\sprintf(
                'Total upload size would exceed limit of %d bytes.',
                $this->aggregateSizeLimit,
            ));
        }
    }

    public function getFileSizeLimit(): int
    {
        return $this->fileSizeLimit;
    }

    public function getMaxFileCount(): int
    {
        return $this->maxFileCount;
    }

    public function getAggregateSizeLimit(): int
    {
        return $this->aggregateSizeLimit;
    }
}
