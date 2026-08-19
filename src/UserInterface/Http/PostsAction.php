<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PostsAction extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
    ) {}

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
