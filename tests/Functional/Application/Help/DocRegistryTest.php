<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Help;

use App\Application\Help\DocArticle;
use App\Application\Help\DocRegistry;
use App\Application\Help\DocSection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

#[Group('functional')]
final class DocRegistryTest extends KernelTestCase
{
    public function testEverySectionHasAtLeastOneArticle(): void
    {
        $sections = array_map(
            static fn(DocArticle $article): string => $article->section->value,
            $this->registry()->all(),
        );

        foreach (DocSection::cases() as $section) {
            static::assertContains($section->value, $sections, 'No article for section ' . $section->value);
        }
    }

    public function testArticlesHaveUniqueSlugsAndRequiredMetadata(): void
    {
        $slugs = [];
        foreach ($this->registry()->all() as $article) {
            static::assertNotContains($article->slug, $slugs, 'Duplicate slug: ' . $article->slug);
            $slugs[] = $article->slug;

            static::assertNotSame('', trim($article->title));
            static::assertNotSame('', trim($article->summary));
            static::assertStringStartsWith('ROLE_', $article->role);
            static::assertNotEmpty($article->keywords, 'Missing keywords: ' . $article->slug);
        }

        static::assertGreaterThanOrEqual(15, count($slugs));
    }

    public function testPrimaryRoutesAndRelatedSlugsResolve(): void
    {
        $registry = $this->registry();
        $router = $this->router();

        foreach ($registry->all() as $article) {
            if ($article->primaryRoute !== null) {
                static::assertNotNull(
                    $router->getRouteCollection()->get($article->primaryRoute),
                    sprintf('Article "%s" points at unknown route "%s".', $article->slug, $article->primaryRoute),
                );
                $router->generate($article->primaryRoute);
            }

            foreach ($article->related as $relatedSlug) {
                static::assertNotNull(
                    $registry->get($relatedSlug),
                    sprintf('Article "%s" links to unknown related article "%s".', $article->slug, $relatedSlug),
                );
            }
        }
    }

    public function testEveryArticleBodyRendersAndOnlyLinksToRealArticles(): void
    {
        $twig = $this->twig();
        $registry = $this->registry();

        foreach ($registry->all() as $article) {
            $html = $twig->render($article->template());
            static::assertNotSame('', trim($html), 'Empty body: ' . $article->slug);

            preg_match_all('#/admin/pomoc/([a-z0-9-]+)#', $html, $matches);
            foreach (array_unique($matches[1]) as $linkedSlug) {
                static::assertNotNull(
                    $registry->get($linkedSlug),
                    sprintf('Article "%s" body links to unknown help article "%s".', $article->slug, $linkedSlug),
                );
            }
        }
    }

    private function registry(): DocRegistry
    {
        $registry = self::getContainer()->get(DocRegistry::class);
        \assert($registry instanceof DocRegistry, 'test container provides the doc registry');

        return $registry;
    }

    private function router(): RouterInterface
    {
        $router = self::getContainer()->get(RouterInterface::class);
        \assert($router instanceof RouterInterface, 'test container provides the router');

        return $router;
    }

    private function twig(): Environment
    {
        $twig = self::getContainer()->get(Environment::class);
        \assert($twig instanceof Environment, 'test container provides twig');

        return $twig;
    }
}
