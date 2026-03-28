<?php

declare(strict_types=1);

namespace Tests\Broadcast\Driver;

use EzPhp\Broadcast\Driver\ArrayDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ArrayDriverTest
 *
 * @package Tests\Driver
 */
#[CoversClass(ArrayDriver::class)]
final class ArrayDriverTest extends TestCase
{
    public function testPublishStoresEventOnChannel(): void
    {
        $driver = new ArrayDriver();
        $driver->publish('notifications', 'UserCreated', ['id' => 1]);

        $events = $driver->eventsOn('notifications');

        $this->assertCount(1, $events);
        $this->assertSame('UserCreated', $events[0]['event']);
        $this->assertSame(['id' => 1], $events[0]['payload']);
    }

    public function testEventsOnReturnsEmptyArrayForUnknownChannel(): void
    {
        $driver = new ArrayDriver();

        $this->assertSame([], $driver->eventsOn('unknown-channel'));
    }

    public function testMultipleEventsOnSameChannelAccumulate(): void
    {
        $driver = new ArrayDriver();
        $driver->publish('ch', 'ev1', ['a' => 1]);
        $driver->publish('ch', 'ev2', ['b' => 2]);

        $events = $driver->eventsOn('ch');
        $this->assertCount(2, $events);
        $this->assertSame('ev1', $events[0]['event']);
        $this->assertSame('ev2', $events[1]['event']);
    }

    public function testEventsOnDifferentChannelsAreIsolated(): void
    {
        $driver = new ArrayDriver();
        $driver->publish('channel-a', 'EvA', []);
        $driver->publish('channel-b', 'EvB', []);

        $this->assertCount(1, $driver->eventsOn('channel-a'));
        $this->assertCount(1, $driver->eventsOn('channel-b'));
        $this->assertSame('EvA', $driver->eventsOn('channel-a')[0]['event']);
        $this->assertSame('EvB', $driver->eventsOn('channel-b')[0]['event']);
    }

    public function testResetClearsAllStoredEvents(): void
    {
        $driver = new ArrayDriver();
        $driver->publish('ch', 'ev', []);
        $driver->reset();

        $this->assertSame([], $driver->eventsOn('ch'));
    }

    public function testResetAllowsRepublishAfterClear(): void
    {
        $driver = new ArrayDriver();
        $driver->publish('ch', 'old', []);
        $driver->reset();
        $driver->publish('ch', 'new', []);

        $events = $driver->eventsOn('ch');
        $this->assertCount(1, $events);
        $this->assertSame('new', $events[0]['event']);
    }

    public function testPublishWithComplexPayload(): void
    {
        $driver = new ArrayDriver();
        $payload = ['user' => ['id' => 5, 'name' => 'Alice'], 'score' => 100];
        $driver->publish('scores', 'ScoreUpdated', $payload);

        $events = $driver->eventsOn('scores');
        $this->assertSame($payload, $events[0]['payload']);
    }
}
