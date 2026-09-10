<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Help;

use App\Application\Help\DocRegistry;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class DocCoverageTest extends KernelTestCase
{
    public function testOperationalFeaturesFoundByTheDocumentationAuditAreCovered(): void
    {
        $registry = self::getContainer()->get(DocRegistry::class);
        \assert($registry instanceof DocRegistry, 'test container provides the doc registry');

        $expectedSlugs = [
            'automatyzacje-i-zadania-w-tle',
            'kolejka-rezerwowa',
            'kody-promocyjne',
            'powiadomienia-i-komunikacja',
            'rezerwacje-manualne-i-szybkie',
            'rezerwacje-statusy-i-termin-platnosci',
            'rozliczenie-platformy',
            'subskrypcje-miesieczne',
        ];

        foreach ($expectedSlugs as $slug) {
            static::assertNotNull($registry->get($slug), 'Missing audited help article: ' . $slug);
        }
    }
}
