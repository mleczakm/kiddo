<?php

declare(strict_types=1);

namespace App\Infrastructure\Monolog;

use Logdash\Logger\Logger as LogdashLogger;
use Logdash\Types\LogLevel as LogdashLogLevel;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;

final class LogdashHandler extends AbstractHandler implements HandlerInterface
{
    public function __construct(
        private readonly LogdashLogger $logdashLogger,
    ) {
        parent::__construct();
    }

    public function handle(LogRecord $record): bool
    {
        $logdashLevel = $this->mapMonologLevelToLogdash($record->level);
        $this->logdashLogger->log($logdashLevel, $record->formatted);

        return false; // Let other handlers process the record
    }

    private function mapMonologLevelToLogdash(Level $level): LogdashLogLevel
    {
        return match ($level) {
            Level::Debug => LogdashLogLevel::DEBUG,
            Level::Info => LogdashLogLevel::INFO,
            Level::Notice => LogdashLogLevel::INFO,
            Level::Warning => LogdashLogLevel::WARN,
            Level::Error => LogdashLogLevel::ERROR,
            Level::Critical => LogdashLogLevel::ERROR,
            Level::Alert => LogdashLogLevel::ERROR,
            Level::Emergency => LogdashLogLevel::ERROR,
        };
    }
}
