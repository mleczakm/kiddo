<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Application\Service\PostFormHandler;
use App\Entity\Post;
use App\Entity\PostStatus;
use App\Entity\User;
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
        private readonly PostFormHandler $formHandler,
        private readonly EntityManagerInterface $entityManager,
        private readonly PostRepository $postRepository,
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
        $this->entityManager->persist($post);

        if ($request->isMethod('POST') && $this->trySave($request, $post)) {
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
            $this->formHandler->save($request, $post, $this->getUser());
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
            return false;
        }

        return true;
    }

    private function renderForm(Post $post, bool $isNew): Response
    {
        return $this->render('admin/posts/edit.html.twig', [
            'post' => $post,
            'isNew' => $isNew,
            'eyebrowOptions' => $this->postRepository->findDistinctEyebrows(),
        ]);
    }
}
