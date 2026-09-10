<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Application\Help\DocArticle;
use App\Application\Help\DocRegistry;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Built-in help & documentation centre. Content is read from Twig templates on disk
 * ({@see DocRegistry}); nothing is stored in the database.
 */
#[IsGranted('ROLE_HOST')]
final class HelpController extends AbstractController
{
    public function __construct(
        private readonly DocRegistry $registry,
        private readonly FeatureManager $featureManager,
    ) {}

    /**
     * @throws \Throwable
     */
    #[Route('/admin/pomoc', name: 'app_admin_help_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->assertEnabled();

        $groups = [];
        foreach ($this->registry->all() as $article) {
            if (!$this->isGranted($article->role)) {
                continue;
            }

            $groups[$article->section->value]['section'] = $article->section;
            $groups[$article->section->value]['articles'][] = $article;
        }

        return $this->render('admin/help/index.html.twig', [
            'groups' => array_values($groups),
        ]);
    }

    /**
     * @throws \Throwable
     */
    #[Route(
        '/admin/pomoc/{slug}',
        name: 'app_admin_help_article',
        methods: ['GET'],
        requirements: [
            'slug' => '[a-z0-9-]+',
        ],
    )]
    public function article(string $slug): Response
    {
        $this->assertEnabled();

        $article = $this->registry->get($slug);
        if (!$article instanceof DocArticle || !$this->isGranted($article->role)) {
            throw $this->createNotFoundException();
        }

        $related = [];
        foreach ($article->related as $relatedSlug) {
            $candidate = $this->registry->get($relatedSlug);
            if ($candidate instanceof DocArticle && $this->isGranted($candidate->role)) {
                $related[] = $candidate;
            }
        }

        return $this->render('admin/help/article.html.twig', [
            'article' => $article,
            'related' => $related,
        ]);
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function assertEnabled(): void
    {
        if (!$this->featureManager->isEnabled('help_center')) {
            throw $this->createNotFoundException();
        }
    }
}
