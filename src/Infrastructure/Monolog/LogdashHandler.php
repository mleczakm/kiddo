<?php

declare(strict_types=1);

namespace App\Infrastructure\Monolog;

use Logdash\Logger\Logger as LogdashLogger;
use Logdash\Types\LogLevel;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class LogdashHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly LogdashLogger $logger,
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $context = $record->context;
        $level = $this->convertMonologLevelToLogdash($record->level);

        // Use Logdash logger to log the message
        $this->logger->log($level, $record->message, $context);
    }

    private function convertMonologLevelToLogdash(Level $level): LogLevel
    {
        return match ($level) {
            Level::Debug => LogLevel::DEBUG,
            Level::Warning => LogLevel::WARN,
            Level::Error, Level::Critical, Level::Alert, Level::Emergency => LogLevel::ERROR,
            default => LogLevel::INFO,
        };
    }
}
