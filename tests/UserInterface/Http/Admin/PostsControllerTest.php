<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
}
