<?php

declare(strict_types=1);

namespace App\Tests\Application\File;

use App\Application\File\FileUploadPolicy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class LegalDocumentFileUploadPolicyTest extends TestCase
{
    public function testPolicyAcceptsPdfAndWordDocuments(): void
    {
        $policy = new FileUploadPolicy('legal_document');
        $policy->assertValidFile('application/pdf', 1024, 0);
        $policy->assertValidFile('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1024, 0);

        $this->addToAssertionCount(2);
    }

    public function testPolicyRejectsSpreadsheet(): void
    {
        $policy = new FileUploadPolicy('legal_document');

        $this->expectException(\InvalidArgumentException::class);
        $policy->assertValidFile('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 1024, 0);
    }
}
