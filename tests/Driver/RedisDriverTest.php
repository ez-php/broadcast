<?php

declare(strict_types=1);

namespace Tests\Broadcast\Driver;

use EzPhp\Broadcast\Driver\RedisDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/**
 * Class RedisDriverTest
 *
 * Integration tests for RedisDriver. Requires a live Redis instance
 * (available via Docker). Tests are skipped automatically when ext-redis
 * is not loaded.
 *
 * @package Tests\Broadcast\Driver
 */
#[CoversClass(RedisDriver::class)]
#[Group('redis')]
final class RedisDriverTest extends TestCase
{
    private ?RedisDriver $driver = null;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not available.');
        }

        try {
            $this->driver = new RedisDriver(
                host: (string) (getenv('REDIS_HOST') ?: '127.0.0.1'),
                port: (int) (getenv('REDIS_PORT') ?: 6379),
            );
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis is not reachable: ' . $e->getMessage());
        }
    }

    public function testPublishDoesNotThrow(): void
    {
        $this->assertNotNull($this->driver);
        $this->driver->publish('test-channel', 'UserCreated', ['id' => 1]);

        $this->addToAssertionCount(1);
    }

    public function testPublishWithEmptyPayloadDoesNotThrow(): void
    {
        $this->assertNotNull($this->driver);
        $this->driver->publish('test-channel', 'PingEvent', []);

        $this->addToAssertionCount(1);
    }

    public function testPublishMultipleEventsDoesNotThrow(): void
    {
        $this->assertNotNull($this->driver);
        $this->driver->publish('ch-a', 'Ev1', ['x' => 1]);
        $this->driver->publish('ch-b', 'Ev2', ['y' => 2]);
        $this->driver->publish('ch-a', 'Ev3', ['z' => 3]);

        $this->addToAssertionCount(1);
    }

    public function testConstructorThrowsWhenExtensionMissing(): void
    {
        if (!extension_loaded('redis')) {
            $this->expectException(RuntimeException::class);
            new RedisDriver();
        } else {
            $this->markTestSkipped('ext-redis is loaded; cannot test missing-extension path.');
        }
    }
}
