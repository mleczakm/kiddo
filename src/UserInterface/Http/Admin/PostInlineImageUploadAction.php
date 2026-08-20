<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Application\File\FileStorageInterface;
use App\Application\File\FileUploadPolicy;
use App\Entity\Post;
use App\Entity\PostFileRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Handle inline image uploads for the article editor.
 * Creates a temporary PostFile attachment and returns the stored file URL.
 */
#[IsGranted('ROLE_MANAGE_CONTENT')]
final class PostInlineImageUploadAction extends AbstractController
{
    public function __construct(
        private readonly FileStorageInterface $fileStorage,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @throws \DomainException
     * @throws \LogicException
     */
    #[Route(
        '/admin/tresci/{id}/inline-image',
        name: 'app_admin_post_inline_image_upload',
        methods: ['POST'],
        requirements: ['id' => '[A-Za-z0-9]+'],
    )]
    public function __invoke(Post $post, Request $request): Response
    {
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $policy = new FileUploadPolicy('article_image');
            $stored = $this->fileStorage->store($file, $policy, $this->getUser());

            $postFile = new \App\Entity\PostFile($post, $stored, PostFileRole::INLINE, $post->files->count());
            $this->em->persist($postFile);
            $this->em->flush();

            $url = $this->urlGenerator->generate(
                'stored_file',
                [
                    'id' => (string) $stored->getId(),
                    'safeName' => $stored->getOriginalName(),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            return new JsonResponse([
                'url' => $url,
                'alt' => '',
                'postFileId' => (string) $postFile->getId(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
