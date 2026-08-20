<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\PostVisibility;
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

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Symfony\Component\HttpFoundation\Exception\UnexpectedValueException
     */
    #[Route(path: [
        'pl' => 'blog',
        'en' => 'blog',
    ], name: 'posts')]
    public function posts(Request $request): Response
    {
        $now = Clock::get()->now();
        $isAuthenticated = $this->getUser() !== null;
        $isStaff = $this->isGranted('ROLE_HOST');
        $page = max(1, $request->query->getInt('page', 1));
        $total = $this->postRepository->countPublished($now, $isAuthenticated, $isStaff);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);

        $items = $this->postRepository->findPublished(
            $now,
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE,
            $isAuthenticated,
            $isStaff,
        );

        $lastModified = null;
        foreach ($items as $item) {
            if ($lastModified === null || $item->updatedAt > $lastModified) {
                $lastModified = $item->updatedAt;
            }
        }
        $lastModified ??= $now;

        $response = new Response();
        // A shared cache entry for this URL is safe only when it was built for
        // an anonymous visitor: an authenticated/staff view can include
        // logged-in-only or staff-only posts, and caching that under the
        // same public URL would leak them to the next anonymous visitor.
        if (!$isAuthenticated) {
            $response->headers->set('X-Cache-Public-Max-Age', '60');
        }
        $response->setLastModified($lastModified);
        $response->setEtag(md5(\sprintf(
            'posts:%d:%d:%d:%s',
            $page,
            $lastPage,
            $total,
            $lastModified->format(\DATE_ATOM),
        )));
        $response->headers->set('Vary', 'Accept-Encoding');

        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->setContent($this->renderView('posts.html.twig', [
            'featured' => $page === 1 ? $items[0] ?? null : null,
            'posts' => $page === 1 ? \array_slice($items, 1) : $items,
            'page' => $page,
            'lastPage' => $lastPage,
        ]));

        return $response;
    }

    /**
     * Unpublished posts 404 for anonymous/regular visitors. A manager with
     * ROLE_MANAGE_CONTENT bypasses the published filter and sees the same
     * "Draft preview" banner as the admin preview route, since both render
     * through this one template — there is deliberately no second view.
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    #[Route(path: [
        'pl' => 'blog/{slug}',
        'en' => 'blog/{slug}',
    ], name: 'post_by_slug')]
    public function postBySlug(string $slug, Request $request): Response
    {
        $now = Clock::get()->now();
        $post = $this->postRepository->findOnePublishedBySlug($slug, $now);

        if ($post !== null && !$post->isVisibleTo($this->getUser() !== null, $this->isGranted('ROLE_HOST'))) {
            $post = null;
        }

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

        $response = new Response();
        // Only a PUBLIC post renders identically for every visitor; a
        // logged-in-only or staff-only post must never enter the shared
        // cache under its public URL, even though this particular request
        // was allowed to see it.
        if ($post->visibility === PostVisibility::PUBLIC) {
            $response->headers->set('X-Cache-Public-Max-Age', '60');
        }
        $response->setLastModified($post->updatedAt);
        $response->setEtag(md5(\sprintf('post:%s:%s', (string) $post->getId(), $post->updatedAt->format(\DATE_ATOM))));
        $response->headers->set('Vary', 'Accept-Encoding');

        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->setContent($this->renderView('post.html.twig', ['post' => $post, 'preview' => false]));

        return $response;
    }
}
