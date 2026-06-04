<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Infrastructure\ZipBomb\ZipBombGenerator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class MaliciousRequestSubscriber
{
    private const array MALICIOUS_PATTERNS = [
        // PHP info/debugging files
        '/phpinfo(\.php)?$/i',
        '/test\.php$/i',
        '/info\.php$/i',
        '/php\.php$/i',
        '/php_info\.php$/i',
        '/i\.php$/i',
        '/pi\.php$/i',
        '/pinfo\.php$/i',
        '/phpinfo2\.php$/i',
        '/php_version\.php$/i',
        '/version\.php$/i',
        '/server-info\.php$/i',
        '/env\.php$/i',
        '/init\.php$/i',
        // Environment files
        '/\.env(\..*)?$/i',
        '/\.aws\//i',
        // WordPress paths
        '/wp-.*/i',
        // Config files
        '/backup_web_config\.txt$/i',
        '/sftp-config\.json$/i',
        '/\.vscode\//i',
        // Database dumps
        '/\.sql$/i',
        // Development files
        '/app_dev\.php\//i',
    ];

    public function __construct(
        private ZipBombGenerator $zipBombGenerator
    ) {}

    #[AsEventListener(event: 'kernel.exception', priority: 200)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (! $exception instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($this->isMaliciousPattern($path)) {
            // Return zip bomb for malicious patterns
            $event->setResponse($this->createZipBomb());
            $event->allowCustomResponseCode();
        }
    }

    private function isMaliciousPattern(string $path): bool
    {
        return array_any(self::MALICIOUS_PATTERNS, fn($pattern) => preg_match($pattern, $path) === 1);
    }

    private function createZipBomb(): Response
    {
        // Generate advanced zip bomb based on USENIX WOOT 2019 paper techniques
        // Creates overlapping files with quoted headers for maximum compression ratio
        $zipData = $this->zipBombGenerator->generate(
            numFiles: 10,
            kernelSize: 100000,
            alphabet: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
        );

        return new Response($zipData, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="backup.zip"',
            'Content-Length' => strlen($zipData),
        ]);
    }
}
