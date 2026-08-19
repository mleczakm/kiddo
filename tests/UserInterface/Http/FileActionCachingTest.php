<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Entity\File;
use App\Entity\Post;
use App\Entity\PostFile;
use App\Entity\PostFileRole;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class FileActionCachingTest extends WebTestCase
{
    public function testConditionalGetOnPublicFileReturns304WhenUnchanged(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'image.jpg', 'image/jpeg', 'test content');
        $post = $this->createPost($em, published: true);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/image.jpg");
        static::assertResponseIsSuccessful();
        $etag = $client->getResponse()->headers->get('ETag');
        static::assertNotNull($etag);

        $client->request('GET', "/pliki/{$file->getId()}/image.jpg", server: ['HTTP_IF_NONE_MATCH' => $etag]);

        static::assertResponseStatusCodeSame(304);
        static::assertSame('', $client->getResponse()->getContent());
    }

    public function testConditionalGetOnPrivateFileNeverReturns304(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $admin = UserAssembler::new()->withRoles('ROLE_MANAGE_CONTENT')->assemble();
        $em->persist($admin);

        $file = $this->createFile($em, 'image.jpg', 'image/jpeg', 'test content');
        $post = $this->createPost($em, published: false);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();
        $client->loginUser($admin);

        $client->request('GET', "/pliki/{$file->getId()}/image.jpg");
        static::assertResponseIsSuccessful();
        static::assertNull($client->getResponse()->headers->get('ETag'));

        // A guessed/replayed validator must not short-circuit a draft file:
        // authorization is checked on every request regardless of caching.
        $client->request('GET', "/pliki/{$file->getId()}/image.jpg", server: ['HTTP_IF_NONE_MATCH' => '"anything"']);

        static::assertResponseIsSuccessful();
        static::assertSame('test content', $client->getResponse()->getContent());
    }

    public function testStaleIfRangeOnVideoFallsBackToFullResponse(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'clip.mp4', 'video/mp4', str_repeat('x', 100));
        $post = $this->createPost($em, published: true);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/clip.mp4", server: [
            'HTTP_RANGE' => 'bytes=0-9',
            'HTTP_IF_RANGE' => '"stale-etag-from-a-previous-version"',
        ]);

        static::assertResponseStatusCodeSame(200);
        static::assertSame(str_repeat('x', 100), $client->getResponse()->getContent());
    }

    public function testFreshIfRangeOnVideoServesPartialContent(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'clip.mp4', 'video/mp4', str_repeat('x', 100));
        $post = $this->createPost($em, published: true);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();

        $checksum = $file->getChecksum();
        $client->request('GET', "/pliki/{$file->getId()}/clip.mp4", server: [
            'HTTP_RANGE' => 'bytes=0-9',
            'HTTP_IF_RANGE' => '"' . $checksum . '"',
        ]);

        static::assertResponseStatusCodeSame(206);
        static::assertSame(str_repeat('x', 10), $client->getResponse()->getContent());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function createFile(EntityManagerInterface $em, string $filename, string $mimeType, string $content): File
    {
        $file = new File($filename, $mimeType, \strlen($content), hash('sha256', $content), base64_encode($content));
        $em->persist($file);
        return $file;
    }

    private function createPost(EntityManagerInterface $em, bool $published): Post
    {
        $author = new User('author@example.com', 'Author');
        $post = new Post($published ? 'Article' : 'Draft Article', $author);
        if ($published) {
            $post->publishAt(Clock::get()->now());
        }
        $em->persist($author);
        $em->persist($post);
        return $post;
    }
}
