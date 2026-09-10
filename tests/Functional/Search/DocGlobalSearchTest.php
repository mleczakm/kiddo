<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Application\Search\GlobalSearchQuery;
use App\Application\Search\SearchReference;
use App\Application\Search\SearchType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Infrastructure\Search\SearchResultHydrator;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminGlobalSearchComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class DocGlobalSearchTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    public function testHelpArticlesShowUpInGlobalSearchForAnAdmin(): void
    {
        $this->client->loginUser($this->user('ROLE_ADMIN'));

        $component = $this->createLiveComponent(AdminGlobalSearchComponent::class, client: $this->client);
        $component->set('query', 'anonimizacja');
        $html = $component->render()->toString();

        static::assertStringContainsString('/admin/pomoc/crm-cykl-zycia-konta', $html);
        static::assertStringContainsString('Cykl życia konta', $html);
        static::assertStringContainsString('data-search-result-icon="doc"', $html);
    }

    public function testHelpSearchRespectsArticleRole(): void
    {
        // "mailing" only appears in dokumenty-publikacja-nowej-wersji (ROLE_SETTINGS).
        $adminComponent = $this->searchAs('ROLE_ADMIN', 'mailing');
        static::assertStringContainsString('/admin/pomoc/dokumenty-publikacja-nowej-wersji', $adminComponent);

        $hostComponent = $this->searchAs('ROLE_HOST', 'mailing');
        static::assertStringNotContainsString('/admin/pomoc/dokumenty-publikacja-nowej-wersji', $hostComponent);
    }

    public function testHydratorBuildsADocResultFromTheRegistry(): void
    {
        $hydrator = self::getContainer()->get(SearchResultHydrator::class);
        \assert($hydrator instanceof SearchResultHydrator, 'test container provides the hydrator');

        $results = $hydrator->hydrate([new SearchReference(SearchType::Doc, 'wprowadzenie')]);

        static::assertCount(1, $results);
        static::assertSame(SearchType::Doc, $results[0]->reference->type);
        static::assertSame('Wprowadzenie do panelu', $results[0]->title);
        static::assertStringContainsString('Pierwsze kroki', $results[0]->subtitle);
    }

    public function testDatabaseSearchStillWorksAlongsideHelp(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface, 'test container provides the ORM entity manager');
        $needle = 'Fasola' . bin2hex(random_bytes(4));
        $client = new User(mb_strtolower($needle) . '@example.test', $needle);
        $em->persist($client);
        $em->flush();

        $search = self::getContainer()->get(GlobalSearchQuery::class);
        \assert($search instanceof GlobalSearchQuery, 'test container provides the global search');
        $references = array_values(iterator_to_array($search->search($needle)));

        static::assertNotEmpty($references);
        static::assertSame(SearchType::Client, $references[0]->type);
    }

    private function searchAs(string $role, string $query): string
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->client->loginUser($this->user($role));

        $component = $this->createLiveComponent(AdminGlobalSearchComponent::class, client: $this->client);
        $component->set('query', $query);

        return $component->render()->toString();
    }

    private function user(string $role): User
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles($role)->assemble();
        $em->persist($user);
        $em->flush();

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $reloaded = $users->find($user->getId());
        static::assertNotNull($reloaded);

        return $reloaded;
    }
}
