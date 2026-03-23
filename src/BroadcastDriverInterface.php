<?php

declare(strict_types=1);

namespace EzPhp\Broadcast;

/**
 * Interface BroadcastDriverInterface
 *
 * Contract for broadcast drivers. Drivers are responsible for persisting
 * or forwarding published events — e.g. writing to a log file, storing
 * in-memory for tests, or pushing to a Redis Pub/Sub channel.
 *
 * @package EzPhp\Broadcast
 */
interface BroadcastDriverInterface
{
    /**
     * Publish an event to the given channel.
     *
     * @param string               $channel The target channel name
     * @param string               $event   The event name
     * @param array<string, mixed> $payload Arbitrary event payload
     *
     * @return void
     */
    public function publish(string $channel, string $event, array $payload): void;
}
