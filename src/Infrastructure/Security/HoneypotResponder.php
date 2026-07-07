<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\ZipBomb\ZipBombGenerator;
use Symfony\Component\HttpFoundation\Response;

final readonly class HoneypotResponder
{
    public function __construct(
        private ZipBombGenerator $zipBombGenerator,
    ) {}

    public function createResponse(): Response
    {
        $zipData = $this->zipBombGenerator->generate(
            numFiles: 10,
            kernelSize: 100000,
            alphabet: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        );

        return new Response($zipData, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="backup.zip"',
            'Content-Length' => (string) strlen($zipData),
        ]);
    }
}
