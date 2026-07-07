<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Infrastructure\Security\HoneypotResponder;
use App\Infrastructure\ZipBomb\ZipBombGenerator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('unit')]
final class HoneypotResponderTest extends TestCase
{
    public function testCreateResponseReturnsZipBomb(): void
    {
        $responder = new HoneypotResponder(new ZipBombGenerator());
        $response = $responder->createResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="backup.zip"', $response->headers->get('Content-Disposition'));
        $this->assertNotEmpty($response->getContent());
    }
}
