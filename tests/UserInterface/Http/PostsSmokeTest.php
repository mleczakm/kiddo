<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Entity\Post;
use App\Entity\PostVisibility;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('smoke')]
final class PostsSmokeTest extends WebTestCase
{
    public function testPublicArticleIsReachableByAnonymousVisitor(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $this->createPublishedPost($em, 'Public Smoke Article', PostVisibility::PUBLIC);
        $em->flush();

        $client->request('GET', '/blog/public-smoke-article');

        static::assertResponseIsSuccessful();
    }

    public function testLoggedInOnlyArticleIsHiddenFromAnonymousVisitor(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $this->createPublishedPost($em, 'Members Smoke Article', PostVisibility::LOGGED_IN);
        $em->flush();

        $client->request('GET', '/blog/members-smoke-article');

        static::assertResponseStatusCodeSame(404);
    }

    public function testLoggedInOnlyArticleIsReachableByAuthenticatedClient(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $this->createPublishedPost($em, 'Members Reachable Article', PostVisibility::LOGGED_IN);
        $customer = UserAssembler::new()->assemble();
        $em->persist($customer);
        $em->flush();

        $client->loginUser($customer);
        $client->request('GET', '/blog/members-reachable-article');

        static::assertResponseIsSuccessful();
    }

    public function testStaffOnlyArticleIsHiddenFromRegularClient(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $this->createPublishedPost($em, 'Staff Smoke Article', PostVisibility::STAFF_ONLY);
        $customer = UserAssembler::new()->assemble();
        $em->persist($customer);
        $em->flush();

        $client->loginUser($customer);
        $client->request('GET', '/blog/staff-smoke-article');

        static::assertResponseStatusCodeSame(404);
    }

    public function testStaffOnlyArticleIsReachableByHost(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $this->createPublishedPost($em, 'Staff Reachable Article', PostVisibility::STAFF_ONLY);
        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $em->persist($host);
        $em->flush();

        $client->loginUser($host);
        $client->request('GET', '/blog/staff-reachable-article');

        static::assertResponseIsSuccessful();
    }

    public function testRestrictedArticleIsExcludedFromAnonymousArticleIndex(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $this->createPublishedPost($em, 'Excluded From Index Article', PostVisibility::STAFF_ONLY);
        $em->flush();

        $crawler = $client->request('GET', '/blog');

        static::assertResponseIsSuccessful();
        static::assertStringNotContainsString('Excluded From Index Article', $crawler->text());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function createPublishedPost(EntityManagerInterface $em, string $title, PostVisibility $visibility): Post
    {
        $author = new User(mb_strtolower(str_replace(' ', '-', $title)) . '-author@example.test', 'Author');
        $post = new Post($title, $author);
        $post->body->updateContent(['type' => 'doc', 'content' => []], '<p>Content</p>');
        $post->publishAt(Clock::get()->now());
        $post->setVisibility($visibility);
        $em->persist($author);
        $em->persist($post);

        return $post;
    }
}
