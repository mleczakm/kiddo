<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class PostsControllerTest extends WebTestCase
{
    public function testAdminCreatesSanitizedDraftAndPreviewsItPrivately(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler
            ->selectButton('Zapisz szkic')
            ->form([
                'title' => 'Fluo Party Guide',
                'eyebrow' => 'Historie z warsztatów',
                'excerpt' => 'Jak przygotować dziecko.',
                'contentHtml' => '<p>Bezpieczna treść</p><script>alert(1)</script>',
            ]);
        $client->submit($form);

        static::assertResponseRedirects();
        $repository = static::getContainer()->get(PostRepository::class);
        static::assertInstanceOf(PostRepository::class, $repository);
        $post = $repository->findOneBy(['slug' => 'fluo-party-guide']);
        static::assertInstanceOf(Post::class, $post);
        static::assertSame('<p>Bezpieczna treść</p>', $post->body->getContentHtml());

        $client->request('GET', '/admin/tresci/' . (string) $post->getId() . '/podglad');

        static::assertResponseIsSuccessful();
        $cacheControl = $client->getResponse()->headers->get('Cache-Control');
        static::assertNotNull($cacheControl);
        static::assertStringContainsString('private', $cacheControl);
        static::assertStringContainsString('no-store', $cacheControl);
        static::assertSelectorTextContains('h1', 'Fluo Party Guide');
        static::assertSelectorExists('meta[name="robots"][content="noindex,nofollow"]');
    }

    public function testAdminSavesSeoAndSocialFields(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler
            ->selectButton('Zapisz szkic')
            ->form([
                'title' => 'SEO Article',
                'contentHtml' => '<p>Treść</p>',
                'seoTitle' => ' Custom SEO title ',
                'seoDescription' => ' Custom SEO description ',
                'canonicalUrl' => 'https://kiddo.example/blog/seo-article',
                'socialTitle' => ' Social title ',
                'socialDescription' => ' Social description ',
            ]);
        /** @var \Symfony\Component\DomCrawler\Field\ChoiceFormField $robotsFollowField */
        $robotsFollowField = $form['robotsFollow'];
        $robotsFollowField->untick();
        $client->submit($form);

        static::assertResponseRedirects();
        $repository = static::getContainer()->get(PostRepository::class);
        static::assertInstanceOf(PostRepository::class, $repository);
        $post = $repository->findOneBy(['slug' => 'seo-article']);
        static::assertInstanceOf(Post::class, $post);
        static::assertSame('Custom SEO title', $post->seo->getSeoTitle());
        static::assertSame('Custom SEO description', $post->seo->getSeoDescription());
        static::assertSame('https://kiddo.example/blog/seo-article', $post->seo->getCanonicalUrl());
        static::assertSame('Social title', $post->seo->getSocialTitle());
        static::assertSame('Social description', $post->seo->getSocialDescription());
        static::assertTrue($post->seo->shouldRobotsIndex());
        static::assertFalse($post->seo->shouldRobotsFollow());
    }

    public function testAdminCannotSaveNonHttpsCanonicalUrl(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler
            ->selectButton('Zapisz szkic')
            ->form([
                'title' => 'Bad Canonical',
                'contentHtml' => '<p>Treść</p>',
                'canonicalUrl' => 'not-a-url',
            ]);
        $client->submit($form);

        static::assertResponseIsSuccessful();
        $repository = static::getContainer()->get(PostRepository::class);
        static::assertInstanceOf(PostRepository::class, $repository);
        static::assertNull($repository->findOneBy(['slug' => 'bad-canonical']));
    }

    public function testAdminSchedulesFuturePublicationInvisibleUntilDue(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $future = Clock::get()->now()->modify('+1 day');
        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler
            ->selectButton('Opublikuj')
            ->form([
                'title' => 'Scheduled Article',
                'contentHtml' => '<p>Treść</p>',
                'publishedAt' => $future->format('Y-m-d\TH:i'),
            ]);
        $client->submit($form);

        static::assertResponseRedirects();
        $repository = static::getContainer()->get(PostRepository::class);
        static::assertInstanceOf(PostRepository::class, $repository);
        $scheduledPost = $repository->findOneBy(['slug' => 'scheduled-article']);
        static::assertInstanceOf(Post::class, $scheduledPost);
        static::assertTrue($scheduledPost->isScheduled());
        static::assertFalse($scheduledPost->isPublished());
        static::assertNull($repository->findOnePublishedBySlug('scheduled-article', Clock::get()->now()));
    }

    public function testAdminSaveAndPublishWithoutDateGoesLiveImmediately(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler
            ->selectButton('Opublikuj')
            ->form(['title' => 'Immediately Live Article', 'contentHtml' => '<p>Treść</p>']);
        $client->submit($form);

        static::assertResponseRedirects();
        $repository = static::getContainer()->get(PostRepository::class);
        static::assertInstanceOf(PostRepository::class, $repository);
        $post = $repository->findOnePublishedBySlug('immediately-live-article', Clock::get()->now());
        static::assertInstanceOf(Post::class, $post);
        static::assertTrue($post->isPublished());
    }

    public function testScheduleActionRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $author = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $post = new Post('Existing article', $author);
        $entityManager->persist($admin);
        $entityManager->persist($author);
        $entityManager->persist($post);
        $entityManager->flush();
        $client->loginUser($admin);

        $tamperedCsrfToken = bin2hex(random_bytes(8));
        $client->request('POST', '/admin/tresci/' . (string) $post->getId() . '/zaplanuj', [
            '_token' => $tamperedCsrfToken,
            'publishedAt' => Clock::get()->now()->modify('+1 day')->format('Y-m-d\TH:i'),
        ]);

        static::assertResponseStatusCodeSame(403);
    }
}
