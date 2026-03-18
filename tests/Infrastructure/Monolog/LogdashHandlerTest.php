<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Monolog;

use PHPUnit\Framework\Attributes\Group;
use App\Infrastructure\Monolog\LogdashHandler;
use Logdash\Logger\Logger;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class LogdashHandlerTest extends TestCase
{
    /**
     * @var \Closure
     */
    private $logMethod;

    /**
     * @var list<array<int|string, mixed>|string>
     */
    private array $logRecords = [];

    protected function setUp(): void
    {
        $this->logMethod = fn(...$args) => $this->logRecords[] = $args;
    }

    public function testItHandlesLogRecords(): void
    {
        $handler = new LogdashHandler(new Logger($this->logMethod), Level::Debug);
        $record = [
            'message' => 'Test log message',
            'context' => [
                'foo' => 'bar',
            ],
            'extra' => [],
        ];

        $logRecord = new LogRecord(
            new \DateTimeImmutable(),
            'test_channel',
            Level::Debug, // Level::Debug
            $record['message'],
            $record['context'],
            $record['extra']
        );

        // Should handle the record (always true for LogdashHandler)
        $this->assertTrue($handler->isHandling($logRecord));

        $handler->handle($logRecord);

        self::assertCount(1, $this->logRecords);
        if (! empty($this->logRecords)) {
            foreach ($this->logRecords as $logRecord) {
                $message = is_array($logRecord) && isset($logRecord[0]) ? (string) $logRecord[0] : '';
                self::assertStringContainsString('Test log message', $message);
                self::assertStringContainsString('DEBUG', $message);
            }
        }
    }

    #[DataProvider('logLevelProvider')]
    public function testItHandlesAllLogLevels(Level $level, string $levelName): void
    {
        $this->logRecords = [];
        $handler = new LogdashHandler(new Logger($this->logMethod));
        $record = [
            'message' => 'Test log message',
            'context' => [
                'foo' => 'bar',
            ],
            'extra' => [],
        ];
        $logRecord = new LogRecord(
            new \DateTimeImmutable(),
            'test_channel',
            $level,
            $record['message'],
            $record['context'],
            $record['extra']
        );
        $this->assertTrue($handler->isHandling($logRecord));
        $handler->handle($logRecord);
        self::assertCount(1, $this->logRecords);
        if (! empty($this->logRecords)) {
            foreach ($this->logRecords as $logRecord) {
                $message = is_array($logRecord) && isset($logRecord[0]) ? (string) $logRecord[0] : '';
                self::assertStringContainsString('Test log message', $message);
                self::assertStringContainsString($levelName, $message);
            }
        }
    }

    /**
     * @return list<array{0: Level, 1: string}>
     */
    public static function logLevelProvider(): array
    {
        return [
            [Level::Debug, 'DEBUG'],
            [Level::Info, 'INFO'],
            [Level::Notice, 'INFO'],
            [Level::Warning, 'WARNING'],
            [Level::Error, 'ERROR'],
            [Level::Critical, 'ERROR'],
            [Level::Alert, 'ERROR'],
            [Level::Emergency, 'ERROR'],
        ];
    }
}
