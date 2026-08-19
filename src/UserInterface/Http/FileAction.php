<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\File\FileStorageInterface;
use App\Entity\File;
use App\Entity\PostFileRole;
use App\Entity\PostStatus;
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

    #[Route('/pliki/{id}/{safeName}', name: 'stored_file', requirements: [
        'id' => '[A-Za-z0-9]{26}',
        'safeName' => '.+',
    ], methods: ['GET', 'HEAD'])]
    public function __invoke(
        Request $request,
        Ulid $id,
        string $safeName,
        FileRepository $fileRepository,
        FileStorageInterface $storage,
    ): Response {
        $file = $fileRepository->find($id);
        if ($file === null) {
            throw $this->createNotFoundException();
        }

        $now = Clock::get()->now();

        $isPublic = $this->isFilePublic($file, $now, $fileRepository);
        if (! $isPublic && ! $this->isGranted('ROLE_MANAGE_CONTENT')) {
            throw $this->createAccessDeniedException();
        }

        $data = $storage->read($file);

        $isInline = $this->isInlineFile($file, $fileRepository);
        $isVideo = str_starts_with($file->getMimeType(), 'video/');

        if ($isVideo && $this->requestsRange($request)) {
            return $this->rangeResponse($request, $data, $file->getMimeType());
        }

        $response = new Response($data, 200, [
            'Content-Type' => $file->getMimeType(),
            'Content-Length' => (string) $file->getSize(),
            'X-Content-Type-Options' => 'nosniff',
        ]);

        if (! $isInline) {
            $safeFilename = $this->sanitizeFilename($file->getOriginalName());
            $response->headers->set('Content-Disposition', "attachment; filename=\"{$safeFilename}\"");
        }

        if ($isPublic) {
            $response->setPublic();
            $response->setMaxAge(300);
            $response->setEtag($file->getChecksum());
            $response->setLastModified($file->getCreatedAt());
        } else {
            $response->setPrivate();
            $response->setNoCache();
        }

        if ($request->getMethod() === 'HEAD') {
            $response->setContent('');
        }

        return $response;
    }

    private function isFilePublic(File $file, \DateTimeImmutable $now, FileRepository $fileRepository): bool
    {
        $posts = $this->getPostsForFile($file, $fileRepository);

        foreach ($posts as $post) {
            if ($post['status'] === PostStatus::PUBLISHED->value && $post['publishedAt'] !== null && $post['publishedAt'] <= $now) {
                return true;
            }
        }

        return false;
    }

    private function isInlineFile(File $file, FileRepository $fileRepository): bool
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select("1")
            ->from('App\Entity\PostFile', 'pf')
            ->where('pf.file = :file')
            ->andWhere('pf.role = :role')
            ->setParameter('file', $file)
            ->setParameter('role', PostFileRole::INLINE->value)
            ->setMaxResults(1)
            ->getQuery();

        return \count($query->getResult()) > 0;
    }

    /** @return list<array{status: string, publishedAt: ?\DateTimeImmutable}> */
    private function getPostsForFile(File $file, FileRepository $fileRepository): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('p.status, p.publishedAt')
            ->from('App\Entity\PostFile', 'pf')
            ->join('pf.post', 'p')
            ->where('pf.file = :file')
            ->setParameter('file', $file)
            ->getQuery();

        return $query->getResult();
    }

    private function requestsRange(Request $request): bool
    {
        return $request->headers->has('Range');
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = trim(basename($filename));
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);

        return $filename;
    }
}
