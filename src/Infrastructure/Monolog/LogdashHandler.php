<?php

declare(strict_types=1);

namespace App\Infrastructure\Monolog;

use Logdash\Logger\Logger as LogdashLogger;
use Logdash\LogLevel;
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
        return match ($level->name) {
            Level::Debug->name => LogLevel::DEBUG,
            Level::Info->name => LogLevel::INFO,
            Level::Notice->name => LogLevel::INFO,
            Level::Warning->name => LogLevel::WARN,
            Level::Error->name => LogLevel::ERROR,
            Level::Critical->name => LogLevel::ERROR,
            Level::Alert->name => LogLevel::ERROR,
            Level::Emergency->name => LogLevel::ERROR,
            default => LogLevel::INFO,
        };
    }
}
