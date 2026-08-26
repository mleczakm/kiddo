<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\UserRepositoryInterface;
use App\Entity\Post;
use App\Entity\PostVisibility;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Orchestrates saving the admin article-editor form: editorial fields,
 * content, SEO, and file attachments/uploads, in the order needed to keep
 * a rejected field from leaving partial writes behind (see the note above
 * the SEO step).
 */
final readonly class PostFormHandler
{
    public function __construct(
        private PostEditor $editor,
        private PostSeoEditor $seoEditor,
        private MenuHookLinkReconciler $menuHookLinkReconciler,
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \DomainException
     * @throws \UnexpectedValueException
     * @throws \ValueError
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     * @throws \Symfony\Component\HttpFoundation\Exception\UnexpectedValueException
     */
    public function save(Request $request, Post $post, ?User $uploadedBy): void
    {
        $eyebrow = $this->nullableField($request, 'eyebrow');
        $excerpt = $this->nullableField($request, 'excerpt');
        $this->editor->updateEditorial($post, (string) $request->request->get('title'), $eyebrow, $excerpt);

        $visibility = PostVisibility::tryFrom($request->request->getString('visibility'));
        if ($visibility !== null) {
            $post->setVisibility($visibility);
        }

        $authorId = $this->nullableField($request, 'author');
        if ($authorId !== null) {
            $author = $this->userRepository->find((int) $authorId);
            if (!$author instanceof User) {
                throw new \InvalidArgumentException('Wybrany autor nie istnieje.');
            }
            $post->setAuthor($author);
        }

        $contentJson = $this->decodeContentJson($request->request->getString('contentJson'));
        $this->editor->updateContent($post, $contentJson, $request->request->getString('contentHtml'));

        // Validation-only steps run first so a bad field never leaves
        // partial writes behind — PostFileManager and
        // reconcileInlineAttachments each flush internally, so anything
        // that can still fail must happen before the first of them runs.
        $this->seoEditor->updateSeo(
            $post,
            new PostSeoInput(
                $this->nullableField($request, 'seoTitle'),
                $this->nullableField($request, 'seoDescription'),
                $this->nullableField($request, 'canonicalUrl'),
                $request->request->getBoolean('robotsIndex'),
                $request->request->getBoolean('robotsFollow'),
            ),
            new PostSocialInput(
                $this->nullableField($request, 'socialTitle'),
                $this->nullableField($request, 'socialDescription'),
            ),
        );

        $this->editor->reconcileInlineAttachments($post, $contentJson);
        $this->reconcileSubmittedFiles($request, $post);

        $uploads = $this->uploadedFiles($request);
        if (\count($uploads) > 0) {
            $this->editor->attachFiles($post, $uploads, $uploadedBy);
        }

        $this->menuHookLinkReconciler->reconcile($post, RequestArrayField::list($request, 'hookSlots'));
    }

    /**
     * @return list<UploadedFile>
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     */
    private function uploadedFiles(Request $request): array
    {
        $files = $request->files->all('files');

        return array_values(array_filter($files, static fn(mixed $file): bool => $file instanceof UploadedFile));
    }

    /** @return array<string, mixed> */
    private function decodeContentJson(string $raw): array
    {
        if ($raw === '') {
            return ['type' => 'doc', 'content' => []];
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded) || array_is_list($decoded)) {
            return ['type' => 'doc', 'content' => []];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \DomainException
     * @throws \ValueError
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     */
    private function reconcileSubmittedFiles(Request $request, Post $post): void
    {
        $fileIds = RequestArrayField::list($request, 'files_id');
        $fileRoles = RequestArrayField::map($request, 'files_role');
        $fileAltTexts = RequestArrayField::map($request, 'files_alt_text');
        $fileCaptions = RequestArrayField::map($request, 'files_caption');
        $removeChecks = RequestArrayField::map($request, 'files_remove');

        $submitted = [];
        foreach ($fileIds as $i => $fileId) {
            if (\array_key_exists($fileId, $removeChecks)) {
                continue;
            }
            $submitted[] = [
                'id' => $fileId,
                'role' => $fileRoles[$fileId] ?? 'attachment',
                'position' => $i,
                'altText' => $fileAltTexts[$fileId] ?? null,
                'caption' => $fileCaptions[$fileId] ?? null,
                'downloadName' => null,
            ];
        }

        if (\count($submitted) > 0 || \count($post->files) > 0) {
            $this->editor->reconcileAttachments($post, $submitted);
        }
    }

    /** @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException */
    private function nullableField(Request $request, string $name): ?string
    {
        $value = $request->request->getString($name);
        return $value === '' ? null : $value;
    }
}
