<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Application\File\FileUploadPolicy;
use App\Entity\File;
use App\Entity\Post;
use App\Entity\PostFile;
use App\Entity\PostFileRole;
use App\Entity\PostStatus;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class FileActionTest extends WebTestCase
{
    public function testAnonymousCanAccessFilesAttachedToPublishedPosts(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'image.jpg', 'image/jpeg', 'test content');
        $post = $this->createPublishedPost($em);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/image.jpg");

        $this->assertResponseIsSuccessful();
        $this->assertSame('image/jpeg', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testAnonymousCannotAccessFilesAttachedToDrafts(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'image.jpg', 'image/jpeg', 'test content');
        $post = $this->createDraftPost($em);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/image.jpg");

        // 401, not 403: Symfony's security exception listener treats "denied
        // while fully unauthenticated" as "please authenticate" rather than
        // "forbidden" — 403 is reserved for an authenticated-but-lacking-role user.
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAdminCanAccessFilesAttachedToDrafts(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $admin = UserAssembler::new()->withRoles('ROLE_MANAGE_CONTENT')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $file = $this->createFile($em, 'image.jpg', 'image/jpeg', 'test content');
        $post = $this->createDraftPost($em);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/image.jpg");

        $this->assertResponseIsSuccessful();
    }

    public function testFileDeliverySetProperHeaders(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'document.pdf', 'application/pdf', 'pdf content');
        $post = $this->createPublishedPost($em);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/document.pdf");

        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
        $this->assertTrue($client->getResponse()->headers->has('X-Content-Type-Options'));
        $this->assertStringContainsString('attachment', $client->getResponse()->headers->get('Content-Disposition') ?? '');
    }

    public function testInlineImageDoesNotForceDownload(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'image.jpg', 'image/jpeg', 'test content');
        $post = $this->createPublishedPost($em);
        new PostFile($post, $file, PostFileRole::INLINE, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/image.jpg");

        $this->assertResponseIsSuccessful();
        $dispositionHeader = $client->getResponse()->headers->get('Content-Disposition') ?? '';
        $this->assertStringNotContainsString('attachment', $dispositionHeader);
    }

    public function testHeadRequestDoesNotReturnBody(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'image.jpg', 'image/jpeg', 'test content');
        $post = $this->createPublishedPost($em);
        new PostFile($post, $file, PostFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('HEAD', "/pliki/{$file->getId()}/image.jpg");

        $this->assertResponseIsSuccessful();
        $this->assertSame('', $client->getResponse()->getContent());
    }

    public function test404ForNonexistentFile(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pliki/0000000000000000000000000000/missing.jpg');
        $this->assertResponseStatusCodeSame(404);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function createFile(EntityManagerInterface $em, string $filename, string $mimeType, string $content): File
    {
        $file = new File(
            $filename,
            $mimeType,
            \strlen($content),
            hash('sha256', $content),
            base64_encode($content),
        );
        $em->persist($file);
        return $file;
    }

    private function createPublishedPost(EntityManagerInterface $em): Post
    {
        $author = new User('author@example.com', 'Author');
        $post = new Post('Article', $author);
        $post->publishAt(Clock::get()->now());
        $em->persist($author);
        $em->persist($post);
        return $post;
    }

    private function createDraftPost(EntityManagerInterface $em): Post
    {
        $author = new User('author@example.com', 'Author');
        $post = new Post('Draft Article', $author);
        $em->persist($author);
        $em->persist($post);
        return $post;
    }
}
