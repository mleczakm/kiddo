<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

final class MaliciousRequestPathMatcher
{
    private const array MALICIOUS_PATTERNS = [
        // PHP files (scanners probing for shells, phpinfo, etc.)
        '/\.php(\/.*)?$/i',
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

    public function matches(string $path): bool
    {
        return array_any(self::MALICIOUS_PATTERNS, fn(string $pattern): bool => preg_match($pattern, $path) === 1);
    }
}
