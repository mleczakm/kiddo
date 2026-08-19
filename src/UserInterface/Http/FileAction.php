<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\File\FileStorageInterface;
use App\Application\File\FileVisibilityChecker;
use App\Entity\File;
use App\Repository\FileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class FileAction extends AbstractController
{
    use RangeResponseTrait;

    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly FileStorageInterface $storage,
        private readonly FileVisibilityChecker $visibility,
    ) {}

    #[Route(
        '/pliki/{id}/{safeName}',
        name: 'stored_file',
        requirements: [
            'id' => '[A-Za-z0-9]{26}',
            'safeName' => '.+',
        ],
        methods: ['GET', 'HEAD'],
    )]
    public function __invoke(Request $request, Ulid $id, string $safeName): Response
    {
        $file = $this->fileRepository->find($id);
        if ($file === null) {
            throw $this->createNotFoundException();
        }

        $now = Clock::get()->now();

        $isPublic = $this->visibility->isPublic($file, $now);
        if (!$isPublic && (!$this->getUser() || !$this->isGranted('ROLE_MANAGE_CONTENT'))) {
            throw $this->createAccessDeniedException();
        }

        $isInline = $this->visibility->isInline($file);
        $isVideo = str_starts_with($file->getMimeType(), 'video/');
        $etag = $isPublic ? $file->getChecksum() : null;

        if ($isVideo && $this->requestsRange($request)) {
            $data = $this->storage->read($file);

            return $this->rangeResponse($request, $data, $file->getMimeType(), $etag, $isPublic ? $file->getCreatedAt() : null);
        }

        $response = new Response('', 200, [
            'Content-Type' => $file->getMimeType(),
            'Content-Length' => (string) $file->getSize(),
            'X-Content-Type-Options' => 'nosniff',
        ]);

        if (!$isInline) {
            $safeFilename = $this->sanitizeFilename($file->getOriginalName());
            $response->headers->set('Content-Disposition', "attachment; filename=\"{$safeFilename}\"");
        }

        if ($this->applyCachePolicy($response, $request, $file, $isPublic, $etag)) {
            return $response;
        }

        if ($request->getMethod() !== 'HEAD') {
            $response->setContent($this->storage->read($file));
        }

        return $response;
    }

    /** Returns true when the response is already complete (304) and delivery should stop there. */
    private function applyCachePolicy(Response $response, Request $request, File $file, bool $isPublic, ?string $etag): bool
    {
        if (!$isPublic) {
            $response->headers->set('Cache-Control', 'private, no-store');
            return false;
        }

        $response->headers->set('X-Cache-Public-Max-Age', '300');
        $response->setEtag($etag);
        $response->setLastModified($file->getCreatedAt());

        return $response->isNotModified($request);
    }

    private function requestsRange(Request $request): bool
    {
        return $request->headers->has('Range');
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = trim(basename($filename));
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return preg_replace('/_+/', '_', $filename);
    }
}
