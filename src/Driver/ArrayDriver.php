<?php

declare(strict_types=1);

namespace EzPhp\Broadcast\Driver;

use EzPhp\Broadcast\BroadcastDriverInterface;

/**
 * Class ArrayDriver
 *
 * Stores published events in-memory, grouped by channel.
 * Designed for assertions in unit and feature tests — inspect
 * broadcasted events without any filesystem or network side-effects.
 *
 * Usage:
 *   $driver = new ArrayDriver();
 *   Broadcast::setBroadcaster(new Broadcaster($driver));
 *   // ... exercise code under test ...
 *   $events = $driver->eventsOn('my-channel');
 *   $this->assertCount(1, $events);
 *   $this->assertSame('UserCreated', $events[0]['event']);
 *
 * @package EzPhp\Broadcast\Driver
 */
final class ArrayDriver implements BroadcastDriverInterface
{
    /**
     * @var array<string, list<array{event: string, payload: array<string, mixed>}>>
     */
    private array $events = [];

    /**
     * @param string               $channel
     * @param string               $event
     * @param array<string, mixed> $payload
     *
     * @return void
     */
    public function publish(string $channel, string $event, array $payload): void
    {
        $this->events[$channel][] = ['event' => $event, 'payload' => $payload];
    }

    /**
     * Return all events published to the given channel.
     *
     * @param string $channel
     *
     * @return list<array{event: string, payload: array<string, mixed>}>
     */
    public function eventsOn(string $channel): array
    {
        return $this->events[$channel] ?? [];
    }

    /**
     * Clear all stored events.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->events = [];
    }
}
