<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Application\Service\PostEditor;
use App\Application\Service\PostSeoEditor;
use App\Application\Service\PostSeoInput;
use App\Application\Service\PostSocialInput;
use App\Entity\Post;
use App\Entity\PostStatus;
use App\Entity\User;
use App\Repository\LessonMetadataRepository;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGE_CONTENT')]
final class PostsController extends AbstractController
{
    public function __construct(
        private readonly PostEditor $editor,
        private readonly PostSeoEditor $seoEditor,
        private readonly EntityManagerInterface $entityManager,
        private readonly LessonMetadataRepository $lessonMetadataRepository,
    ) {}

    /** @throws \UnexpectedValueException */
    #[Route('/admin/tresci', name: 'app_admin_posts', methods: ['GET'])]
    public function index(PostRepository $repository): Response
    {
        return $this->render('admin/posts/index.html.twig', [
            'posts' => $repository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }

    /** @throws \Throwable */
    #[Route('/admin/tresci/nowa', name: 'app_admin_post_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User, 'A content manager must be authenticated.');
        $post = new Post('Nowy artykuł', $user);

        if ($request->isMethod('POST') && $this->trySave($request, $post)) {
            $this->entityManager->persist($post);
            $this->entityManager->flush();
            $this->addFlash('success', 'Artykuł został zapisany.');

            return $this->redirectToRoute('app_admin_post_edit', ['id' => (string) $post->getId()]);
        }

        return $this->renderForm($post, true);
    }

    /** @throws \Throwable */
    #[Route(
        '/admin/tresci/{id}/edycja',
        name: 'app_admin_post_edit',
        methods: ['GET', 'POST'],
        requirements: ['id' => '[A-Za-z0-9]+'],
    )]
    public function edit(Post $post, Request $request): Response
    {
        if ($request->isMethod('POST') && $this->trySave($request, $post)) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Artykuł został zapisany.');

            return $this->redirectToRoute('app_admin_post_edit', ['id' => (string) $post->getId()]);
        }

        return $this->renderForm($post, false);
    }

    /** @throws \Throwable */
    #[Route(
        '/admin/tresci/{id}/toggle',
        name: 'app_admin_post_toggle',
        methods: ['POST'],
        requirements: ['id' => '[A-Za-z0-9]+'],
    )]
    public function toggle(Post $post, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(
            'toggle_post_' . (string) $post->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        match ($post->status) {
            PostStatus::DRAFT => $post->publishAt(Clock::get()->now()),
            PostStatus::PUBLISHED => $post->unpublish(),
        };
        $this->entityManager->flush();

        return $this->redirectToRoute('app_admin_posts');
    }

    /** @throws \Throwable */
    #[Route(
        '/admin/tresci/{id}/zaplanuj',
        name: 'app_admin_post_schedule',
        methods: ['POST'],
        requirements: ['id' => '[A-Za-z0-9]+'],
    )]
    public function schedule(Post $post, Request $request): Response
    {
        if (!$this->isCsrfTokenValid(
            'schedule_post_' . (string) $post->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $publishedAt = $request->request->getString('publishedAt');
        $scheduledAt = $publishedAt === '' ? false : \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $publishedAt);
        if ($scheduledAt === false) {
            $this->addFlash('error', 'Podaj poprawną datę i godzinę publikacji.');
            return $this->redirectToRoute('app_admin_post_edit', ['id' => (string) $post->getId()]);
        }

        $post->publishAt($scheduledAt);
        $this->entityManager->flush();
        $this->addFlash('success', 'Publikacja została zaplanowana.');

        return $this->redirectToRoute('app_admin_post_edit', ['id' => (string) $post->getId()]);
    }

    #[Route(
        '/admin/tresci/{id}/podglad',
        name: 'app_admin_post_preview',
        methods: ['GET'],
        requirements: ['id' => '[A-Za-z0-9]+'],
    )]
    public function preview(Post $post): Response
    {
        $response = $this->render('post.html.twig', ['post' => $post, 'preview' => true]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /** @throws \Throwable */
    private function trySave(Request $request, Post $post): bool
    {
        if (!$this->isCsrfTokenValid('edit_post', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $eyebrow = $request->request->getString('eyebrow');
            $excerpt = $request->request->getString('excerpt');
            $linkedWorkshopSlug = $request->request->getString('linkedWorkshopSlug');
            $this->editor->updateEditorial(
                $post,
                (string) $request->request->get('title'),
                $eyebrow === '' ? null : $eyebrow,
                $excerpt === '' ? null : $excerpt,
                $linkedWorkshopSlug === '' ? null : $linkedWorkshopSlug,
            );
            $contentJsonStr = $request->request->getString('contentJson');
            try {
                $contentJson = $contentJsonStr ? json_decode($contentJsonStr, true) : ['type' => 'doc', 'content' => []];
            } catch (\Exception) {
                $contentJson = ['type' => 'doc', 'content' => []];
            }
            $this->editor->updateContent(
                $post,
                $contentJson,
                $request->request->getString('contentHtml'),
            );

            $this->editor->reconcileInlineAttachments($post, $contentJson);
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

            $uploads = $request->files->all()['files'] ?? [];
            if (\count($uploads) > 0) {
                $this->editor->attachFiles($post, $uploads, $this->getUser());
            }

            $this->handleFileReconciliation($request, $post);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
            return false;
        }

        return true;
    }

    private function handleFileReconciliation(Request $request, Post $post): void
    {
        $fileIds = $request->request->all()['files_id'] ?? [];
        $fileRoles = $request->request->all()['files_role'] ?? [];
        $removeChecks = $request->request->all()['files_remove'] ?? [];

        $submitted = [];
        foreach ($fileIds as $i => $fileId) {
            if (isset($removeChecks[$fileId])) {
                continue;
            }
            $submitted[] = [
                'id' => (string) $fileId,
                'role' => $fileRoles[$fileId] ?? 'attachment',
                'position' => $i,
                'altText' => null,
                'caption' => null,
                'downloadName' => null,
            ];
        }

        if (\count($submitted) > 0 || \count($post->files) > 0) {
            $this->editor->reconcileAttachments($post, $submitted);
        }
    }

    private function renderForm(Post $post, bool $isNew): Response
    {
        return $this->render('admin/posts/edit.html.twig', [
            'post' => $post,
            'isNew' => $isNew,
            'workshopOptions' => $this->lessonMetadataRepository->findDistinctSlugsForOptions(),
        ]);
    }

    /** @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException */
    private function nullableField(Request $request, string $name): ?string
    {
        $value = $request->request->getString($name);
        return $value === '' ? null : $value;
    }
}
