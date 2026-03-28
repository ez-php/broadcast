<?php

declare(strict_types=1);

namespace Tests\Broadcast\Driver;

use EzPhp\Broadcast\Driver\NullDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class NullDriverTest
 *
 * @package Tests\Driver
 */
#[CoversClass(NullDriver::class)]
final class NullDriverTest extends TestCase
{
    public function testPublishDoesNotThrow(): void
    {
        $driver = new NullDriver();
        $driver->publish('channel', 'event', ['key' => 'value']);

        $this->addToAssertionCount(1);
    }

    public function testPublishWithEmptyPayloadDoesNotThrow(): void
    {
        $driver = new NullDriver();
        $driver->publish('channel', 'event', []);

        $this->addToAssertionCount(1);
    }

    public function testMultiplePublishCallsDoNotThrow(): void
    {
        $driver = new NullDriver();
        $driver->publish('a', 'ev1', []);
        $driver->publish('b', 'ev2', ['x' => 1]);
        $driver->publish('c', 'ev3', ['y' => 2]);

        $this->addToAssertionCount(1);
    }
}
