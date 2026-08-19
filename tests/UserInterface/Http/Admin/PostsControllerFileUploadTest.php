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
    public function testAdminCanUploadFilesToNewArticle(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_MANAGE_CONTENT')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $file = $this->createUploadedFile('test image content', 'test.jpg', 'image/jpeg');
        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler->selectButton('Zapisz szkic')->form([
            'title' => 'Article with File',
            'contentHtml' => '<p>Content</p>',
        ]);

        $form['files[]'] = $file;
        $client->submit($form);

        static::assertResponseRedirects();
        $repository = static::getContainer()->get(PostRepository::class);
        $post = $repository->findOneBy(['slug' => 'article-with-file']);
        static::assertInstanceOf(Post::class, $post);
        static::assertCount(1, $post->files);
        static::assertSame(PostFileRole::ATTACHMENT, $post->files[0]->getRole());
    }

    public function testAdminCanRemoveFilesAfterUpload(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_MANAGE_CONTENT')->assemble();
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);

        $file = $this->createUploadedFile('test content', 'test.jpg', 'image/jpeg');
        $crawler = $client->request('GET', '/admin/tresci/nowa');
        $form = $crawler->selectButton('Zapisz szkic')->form([
            'title' => 'Article',
            'contentHtml' => '<p>Content</p>',
        ]);
        $form['files[]'] = $file;
        $client->submit($form);

        static::assertResponseRedirects();
        $postRepository = static::getContainer()->get(PostRepository::class);
        $post = $postRepository->findOneBy(['slug' => 'article']);
        static::assertCount(1, $post->files);

        $fileId = (string) $post->files[0]->getFile()->getId();

        $crawler = $client->request('GET', '/admin/tresci/' . (string) $post->getId() . '/edycja');
        $form = $crawler->selectButton('Zapisz szkic')->form([
            'title' => 'Article',
            'contentHtml' => '<p>Updated</p>',
        ]);

        $form['files_id'] = [$fileId];
        $form['files_remove'][$fileId] = true;
        $client->submit($form);

        $postRepository->refresh($post);
        static::assertCount(0, $post->files);

        $fileRepository = static::getContainer()->get(FileRepository::class);
        $file = $fileRepository->find($fileId);
        static::assertNotNull($file, 'File should still exist (not orphaned yet)');
    }

    private function createUploadedFile(string $content, string $filename, string $mimeType): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $content);

        return new UploadedFile(
            $tempFile,
            $filename,
            $mimeType,
            filesize($tempFile) ?: null,
            true,
        );
    }
}
