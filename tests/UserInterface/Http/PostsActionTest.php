<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Entity\Post;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class PostsActionTest extends WebTestCase
{
    public function testPublishedArticleSetsPublicCacheHeaders(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $post = $this->createPublishedPost($em, 'Public Article');
        $em->flush();

        $client->request('GET', '/blog/public-article');

        static::assertResponseIsSuccessful();
        $headers = $client->getResponse()->headers;
        static::assertStringContainsString('public', (string) $headers->get('Cache-Control'));
        static::assertStringContainsString('max-age=60', (string) $headers->get('Cache-Control'));
        static::assertNotNull($headers->get('ETag'));
        static::assertNotNull($headers->get('Last-Modified'));
    }

    public function testConditionalGetOnArticleReturns304WhenUnchanged(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $post = $this->createPublishedPost($em, 'Cacheable Article');
        $em->flush();

        $client->request('GET', '/blog/cacheable-article');
        static::assertResponseIsSuccessful();
        $etag = $client->getResponse()->headers->get('ETag');
        static::assertNotNull($etag);

        $client->request('GET', '/blog/cacheable-article', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        static::assertResponseStatusCodeSame(304);
        static::assertSame('', $client->getResponse()->getContent());
    }

    public function testEditingArticleChangesEtag(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $post = $this->createPublishedPost($em, 'Changing Article');
        $em->flush();
        $postId = (string) $post->getId();

        $client->request('GET', '/blog/changing-article');
        $originalEtag = $client->getResponse()->headers->get('ETag');
        static::assertNotNull($originalEtag);

        $client->loginUser($admin);
        $crawler = $client->request('GET', "/admin/tresci/{$postId}/edycja");
        $form = $crawler
            ->selectButton('Zapisz szkic')
            ->form(['contentHtml' => '<p>Updated content</p>']);
        $client->submit($form);
        static::assertResponseRedirects();

        $client->request('GET', '/blog/changing-article', server: ['HTTP_IF_NONE_MATCH' => $originalEtag]);

        static::assertResponseIsSuccessful();
        static::assertNotSame($originalEtag, $client->getResponse()->headers->get('ETag'));
    }

    public function testDraftPreviewForManagerIsPrivateNoStore(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $admin = UserAssembler::new()->withRoles('ROLE_MANAGE_CONTENT')->assemble();
        $em->persist($admin);
        $post = $this->createDraftPost($em, 'Draft Article');
        $em->flush();
        $client->loginUser($admin);

        $client->request('GET', '/blog/draft-article');

        static::assertResponseIsSuccessful();
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        static::assertStringContainsString('private', $cacheControl);
        static::assertStringContainsString('no-store', $cacheControl);
        static::assertNull($client->getResponse()->headers->get('ETag'));
    }

    public function testAnonymousGets404ForDraftArticle(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $this->createDraftPost($em, 'Hidden Draft');
        $em->flush();

        $client->request('GET', '/blog/hidden-draft');

        static::assertResponseStatusCodeSame(404);
    }

    public function testPublishedIndexSetsPublicCacheHeadersAndReturns304(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $this->createPublishedPost($em, 'Index Article');
        $em->flush();

        $client->request('GET', '/blog');

        static::assertResponseIsSuccessful();
        $headers = $client->getResponse()->headers;
        static::assertStringContainsString('public', (string) $headers->get('Cache-Control'));
        $etag = $headers->get('ETag');
        static::assertNotNull($etag);

        $client->request('GET', '/blog', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        static::assertResponseStatusCodeSame(304);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function createPublishedPost(EntityManagerInterface $em, string $title): Post
    {
        $author = new User('author@example.com', 'Author');
        $post = new Post($title, $author);
        $post->body->updateContent(['type' => 'doc', 'content' => []], '<p>Content</p>');
        $post->publishAt(Clock::get()->now());
        $em->persist($author);
        $em->persist($post);

        return $post;
    }

    private function createDraftPost(EntityManagerInterface $em, string $title): Post
    {
        $author = new User('draft-author@example.com', 'Draft Author');
        $post = new Post($title, $author);
        $post->body->updateContent(['type' => 'doc', 'content' => []], '<p>Content</p>');
        $em->persist($author);
        $em->persist($post);

        return $post;
    }
}
