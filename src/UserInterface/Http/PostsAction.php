<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PostsAction extends AbstractController
{
    private const int PER_PAGE = 9;

    public function __construct(
        private readonly PostRepository $postRepository,
    ) {}

    #[Route(path: [
        'pl' => 'blog',
        'en' => 'blog',
    ], name: 'posts')]
    public function posts(Request $request): Response
    {
        $now = Clock::get()->now();
        $page = max(1, $request->query->getInt('page', 1));
        $total = $this->postRepository->countPublished($now);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);

        $items = $this->postRepository->findPublished($now, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $response = $this->render('posts.html.twig', [
            'featured' => $page === 1 ? $items[0] ?? null : null,
            'posts' => $page === 1 ? \array_slice($items, 1) : $items,
            'page' => $page,
            'lastPage' => $lastPage,
        ]);
        $response->setPublic();
        $response->setMaxAge(60);
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }

    /**
     * Unpublished posts 404 for anonymous/regular visitors. A manager with
     * ROLE_MANAGE_CONTENT bypasses the published filter and sees the same
     * "Draft preview" banner as the admin preview route, since both render
     * through this one template — there is deliberately no second view.
     */
    #[Route(path: [
        'pl' => 'blog/{slug}',
        'en' => 'blog/{slug}',
    ], name: 'post_by_slug')]
    public function postBySlug(string $slug): Response
    {
        $now = Clock::get()->now();
        $post = $this->postRepository->findOnePublishedBySlug($slug, $now);

        if ($post === null) {
            if (!$this->isGranted('ROLE_MANAGE_CONTENT')) {
                throw $this->createNotFoundException();
            }

            $post = $this->postRepository->findOneBy(['slug' => $slug]);
            if ($post === null) {
                throw $this->createNotFoundException();
            }

            $response = $this->render('post.html.twig', ['post' => $post, 'preview' => true]);
            $response->setPrivate();
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        $response = $this->render('post.html.twig', ['post' => $post, 'preview' => false]);
        $response->setPublic();
        $response->setMaxAge(60);
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}
