<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Entity\Post;
use App\Entity\PostFileRole;
use App\Repository\FileRepository;
use App\Repository\PostRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('functional')]
final class PostsControllerFileUploadTest extends WebTestCase
{
    /**
     * A real, minimal valid 1x1 JPEG (base64), so the storage layer's finfo-based
     * MIME sniffing (which never trusts a client-declared type) accepts it.
     */
    private const string MINIMAL_JPEG_BASE64 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD/AD8//9k=';

    public function testAdminCanUploadFilesToNewArticle(): void
    {
        $client = static::createClient();
        $entityManager = $this->getEntityManager();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $file = $this->createUploadedJpeg('test.jpg');
        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler
            ->selectButton('Zapisz szkic')
            ->form([
                'title' => 'Article with File',
                'contentHtml' => '<p>Content</p>',
            ]);

        $form['files'] = [$file];
        $client->submit($form);

        static::assertResponseRedirects();
        $post = $this->getPostRepository()->findOneBy(['slug' => 'article-with-file']);
        static::assertInstanceOf(Post::class, $post);
        static::assertCount(1, $post->files);
        static::assertSame(PostFileRole::ATTACHMENT, $post->files[0]->getRole());
    }

    public function testAdminCanRemoveFilesAfterUpload(): void
    {
        $client = static::createClient();
        $entityManager = $this->getEntityManager();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $file = $this->createUploadedJpeg('test.jpg');
        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler
            ->selectButton('Zapisz szkic')
            ->form([
                'title' => 'Article',
                'contentHtml' => '<p>Content</p>',
            ]);
        $form['files'] = [$file];
        $client->submit($form);

        static::assertResponseRedirects();
        $postRepository = $this->getPostRepository();
        $post = $postRepository->findOneBy(['slug' => 'article']);
        static::assertInstanceOf(Post::class, $post);
        static::assertCount(1, $post->files);

        // The template's files_id[]/files_remove[...] key by PostFile's own
        // id (the join row), not the underlying File's id.
        $postFileId = (string) $post->files[0]->getId();
        $fileId = (string) $post->files[0]->getFile()->getId();

        $crawler = $client->request('GET', '/admin/tresci/' . (string) $post->getId() . '/edycja');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        // Built as a raw request instead of via the DomCrawler Form API:
        // Symfony's FormFieldRegistry cannot address a dynamically-keyed
        // checkbox name like files_remove[{ulid}] that wasn't present in
        // the form's initial field list it builds from the DOM.
        $client->request('POST', '/admin/tresci/' . (string) $post->getId() . '/edycja', [
            '_token' => $csrfToken,
            'title' => 'Article',
            'contentHtml' => '<p>Updated</p>',
            'files_id' => [$postFileId],
            'files_remove' => [$postFileId => '1'],
        ]);

        $post = $postRepository->findOneBy(['slug' => 'article']);
        static::assertInstanceOf(Post::class, $post);
        static::assertCount(0, $post->files);

        $file = $this->getFileRepository()->find($fileId);
        static::assertNotNull($file, 'File should still exist (not orphaned yet)');
    }

    /**
     * Generates a real, minimal valid JPEG so the storage layer's finfo-based
     * MIME sniffing (which never trusts a client-declared type) accepts it.
     */
    private function createUploadedJpeg(string $filename): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        file_put_contents($tempFile, base64_decode(self::MINIMAL_JPEG_BASE64, true));

        return new UploadedFile($tempFile, $filename, 'image/jpeg', filesize($tempFile) ?: null, true);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function getPostRepository(): PostRepository
    {
        /** @var PostRepository */
        return static::getContainer()->get(PostRepository::class);
    }

    private function getFileRepository(): FileRepository
    {
        /** @var FileRepository */
        return static::getContainer()->get(FileRepository::class);
    }
}
