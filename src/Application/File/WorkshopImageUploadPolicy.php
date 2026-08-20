<?php

declare(strict_types=1);

namespace App\Application\File;

/**
 * Upload policy for workshop images/videos (legacy workshops).
 * Preserves existing size limits: 3MB images, 20MB videos.
 * Used by WorkshopEditorComponent to validate new uploads.
 */
final readonly class WorkshopImageUploadPolicy
{
    private FileUploadPolicy $imagePolicy;
    private FileUploadPolicy $videoPolicy;

    /** @throws \InvalidArgumentException */
    public function __construct()
    {
        $this->imagePolicy = new FileUploadPolicy(
            'article_image',
            fileSizeLimit: 3 * 1024 * 1024,
            maxFileCount: 1,
            aggregateSizeLimit: 3 * 1024 * 1024,
        );

        $this->videoPolicy = new FileUploadPolicy(
            'article_video',
            fileSizeLimit: 20 * 1024 * 1024,
            maxFileCount: 1,
            aggregateSizeLimit: 20 * 1024 * 1024,
        );
    }

    /**
     * Validate an image or video upload.
     *
     * @throws \InvalidArgumentException
     */
    public function assertValidUpload(string $mimeType, int $decodedSize): void
    {
        if (str_starts_with($mimeType, 'video/')) {
            $this->videoPolicy->assertValidFile($mimeType, $decodedSize, 0);
        } else {
            $this->imagePolicy->assertValidFile($mimeType, $decodedSize, 0);
        }
    }

    /**
     * Get the supported image MIME types.
     *
     * @return list<string>
     */
    public static function supportedImageMimes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    }

    /**
     * Get the supported video MIME types.
     *
     * @return list<string>
     */
    public static function supportedVideoMimes(): array
    {
        return ['video/mp4', 'video/webm'];
    }
}
