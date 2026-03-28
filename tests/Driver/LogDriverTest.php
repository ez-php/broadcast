<?php

declare(strict_types=1);

namespace Tests\Broadcast\Driver;

use EzPhp\Broadcast\Driver\LogDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class LogDriverTest
 *
 * @package Tests\Driver
 */
#[CoversClass(LogDriver::class)]
final class LogDriverTest extends TestCase
{
    private string $logFile = '';

    protected function setUp(): void
    {
        parent::setUp();
        $tmp = tempnam(sys_get_temp_dir(), 'ez-broadcast-log-');
        $this->assertIsString($tmp);
        $this->logFile = $tmp;
    }

    protected function tearDown(): void
    {
        if ($this->logFile !== '' && file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        parent::tearDown();
    }

    public function testPublishWritesLineToLogFile(): void
    {
        $driver = new LogDriver($this->logFile);
        $driver->publish('orders', 'OrderPlaced', ['id' => 99]);

        $content = file_get_contents($this->logFile);
        $this->assertIsString($content);
        $this->assertStringContainsString('channel=orders', $content);
        $this->assertStringContainsString('event=OrderPlaced', $content);
        $this->assertStringContainsString('"id":99', $content);
    }

    public function testPublishAppendsOnMultipleCalls(): void
    {
        $driver = new LogDriver($this->logFile);
        $driver->publish('ch', 'ev1', []);
        $driver->publish('ch', 'ev2', []);

        $lines = array_filter(explode(PHP_EOL, (string) file_get_contents($this->logFile)));
        $this->assertCount(2, array_values($lines));
    }

    public function testPublishCreatesDirectoryWhenMissing(): void
    {
        $dir = sys_get_temp_dir() . '/ez-broadcast-test-dir-' . uniqid('', true);
        $logPath = $dir . '/broadcast.log';

        $driver = new LogDriver($logPath);
        $driver->publish('ch', 'ev', []);

        $this->assertFileExists($logPath);

        unlink($logPath);
        rmdir($dir);
    }

    public function testPublishIncludesTimestamp(): void
    {
        $driver = new LogDriver($this->logFile);
        $driver->publish('ch', 'ev', []);

        $content = (string) file_get_contents($this->logFile);
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
    }

    public function testPublishWithEmptyPayloadWritesEmptyJsonObject(): void
    {
        $driver = new LogDriver($this->logFile);
        $driver->publish('ch', 'ev', []);

        $content = (string) file_get_contents($this->logFile);
        $this->assertStringContainsString('payload=[]', $content);
    }

    public function testPublishWithEmptyPathWritesViaErrorLog(): void
    {
        $tmpLog = tempnam(sys_get_temp_dir(), 'ez-broadcast-errlog-');
        $this->assertIsString($tmpLog);

        $previous = ini_set('error_log', $tmpLog);

        try {
            $driver = new LogDriver('');
            $driver->publish('ch', 'ErrorLogEvent', ['k' => 'v']);

            $content = file_get_contents($tmpLog);
            $this->assertIsString($content);
            $this->assertStringContainsString('ErrorLogEvent', $content);
        } finally {
            ini_set('error_log', is_string($previous) ? $previous : '');
            unlink($tmpLog);
        }
    }
}
