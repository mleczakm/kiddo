<?php

declare(strict_types=1);

namespace App\Application\File;

use InvalidArgumentException;

/**
 * Defines upload constraints by usage context:
 * article_image: pictures in cover/inline/attachments (JPEG, PNG, WebP, AVIF)
 * article_video: playable media with Range support (MP4, WebM, Ogg)
 * article_document: downloadable files (PDF, DOCX, XLSX, PPTX)
 * legal_document: published legal documents (PDF or DOCX)
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
        [$size, $count, $total, $types] = match ($usage) {
            'article_image' => [8, 10, 50, ['image/jpeg', 'image/png', 'image/webp', 'image/avif']],
            'article_video' => [50, 5, 100, ['video/mp4', 'video/webm', 'video/ogg']],
            'article_document' => [
                20,
                10,
                100,
                [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                ],
            ],
            'legal_document' => [
                20,
                1,
                20,
                [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
            ],
            default => throw new InvalidArgumentException("Unknown usage: {$usage}"),
        };

        $this->fileSizeLimit = $fileSizeLimit ?? ($size * 1024 * 1024);
        $this->maxFileCount = $maxFileCount ?? $count;
        $this->aggregateSizeLimit = $aggregateSizeLimit ?? ($total * 1024 * 1024);
        $this->allowedMimeTypes = $types;
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
