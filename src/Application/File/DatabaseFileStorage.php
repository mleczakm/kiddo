<?php

declare(strict_types=1);

namespace App\Application\File;

use App\Entity\File;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class DatabaseFileStorage implements FileStorageInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /** @throws InvalidArgumentException */
    #[\Override]
    public function store(UploadedFile $upload, FileUploadPolicy $policy, ?User $uploadedBy = null): File
    {
        $originalName = \basename($upload->getClientOriginalName());
        $content = file_get_contents($upload->getRealPath());
        if ($content === false) {
            throw new InvalidArgumentException('Unable to read the uploaded file.');
        }
        $decodedSize = \strlen($content);

        $mimeType = $this->determineMimeType($content, (string) $upload->getMimeType());
        $policy->assertValidFile($mimeType, $decodedSize, 0);

        $checksum = hash('sha256', $content, false);
        $data = base64_encode($content);

        $file = new File($originalName, $mimeType, $decodedSize, $checksum, $data);
        if ($uploadedBy !== null) {
            $file->setUploadedBy($uploadedBy);
        }

        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    /** @throws InvalidArgumentException */
    #[\Override]
    public function read(File $file): string
    {
        $data = $file->getData();
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('File contains invalid base64 data.');
        }

        return $decoded;
    }

    #[\Override]
    public function delete(File $file): void
    {
        $this->em->remove($file);
        $this->em->flush();
    }

    private function determineMimeType(string $content, string $clientMimeType): string
    {
        if (\function_exists('finfo_buffer')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $content);
                finfo_close($finfo);
                if ($detected !== false && $detected !== '') {
                    return $detected;
                }
            }
        }

        if ($clientMimeType && $clientMimeType !== 'application/octet-stream') {
            return $clientMimeType;
        }

        return 'application/octet-stream';
    }
}
