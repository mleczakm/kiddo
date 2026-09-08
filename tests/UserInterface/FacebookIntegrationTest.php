<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

use App\Tests\Assembler\SettingAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class FacebookIntegrationTest extends WebTestCase
{
    private const string FACEBOOK_URL = 'https://www.facebook.com/profile.php?id=61564631314322';

    public function testHomepageExposesFacebookLinksAndSeoMetadataWhenConfigured(): void
    {
        $client = static::createClient();
        $this->persistOrganizationDetails(self::FACEBOOK_URL);

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        // SEO: rel="me", Open Graph publisher and Organization JSON-LD sameAs.
        $this->assertSelectorExists('link[rel="me"][href*="facebook.com"]');
        $this->assertSelectorExists('meta[property="article:publisher"][content*="facebook.com"]');

        $jsonLd = $client->getCrawler()->filter('script[type="application/ld+json"]')->text();
        static::assertStringContainsString('"sameAs"', $jsonLd);
        // json_encode escapes the slashes, so match on the host-less tail.
        static::assertStringContainsString('profile.php?id=61564631314322', $jsonLd);

        // Visible entry points: footer + top navigation.
        $this->assertSelectorExists('footer a[href*="facebook.com"][rel*="me"]');
        $this->assertSelectorExists('header a[href*="facebook.com"]');
    }

    public function testNoFacebookTouchpointsWhenUrlIsNotConfigured(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('link[rel="me"]');
        $this->assertSelectorNotExists('a[href*="facebook.com"]');
    }

    public function testBlankFacebookUrlRemovesEveryFacebookTouchpoint(): void
    {
        $client = static::createClient();
        $this->persistOrganizationDetails('');

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('link[rel="me"]');
        $this->assertSelectorNotExists('a[href*="facebook.com"]');
    }

    private function persistOrganizationDetails(string $facebookUrl): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist(SettingAssembler::new()->asOrganizationDetails(facebookUrl: $facebookUrl)->assemble());
        $em->flush();
    }
}
