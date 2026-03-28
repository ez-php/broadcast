<?php

declare(strict_types=1);

namespace EzPhp\Broadcast;

/**
 * Class Broadcaster
 *
 * Orchestrates publishing of broadcastable events to the configured driver.
 * Accepts either a BroadcastableInterface object (extracts channel, event name,
 * and payload automatically) or explicit channel/event/payload values.
 *
 * @package EzPhp\Broadcast
 */
final class Broadcaster
{
    /**
     * Broadcaster Constructor
     *
     * @param BroadcastDriverInterface $driver
     */
    public function __construct(private readonly BroadcastDriverInterface $driver)
    {
    }

    /**
     * Publish a broadcastable event using its own channel, name, and payload.
     *
     * @param BroadcastableInterface $event
     *
     * @return void
     */
    public function event(BroadcastableInterface $event): void
    {
        $this->driver->publish(
            $event->broadcastOn(),
            $event->broadcastAs(),
            $event->broadcastWith(),
        );
    }

    /**
     * Publish an event directly to the given channel.
     *
     * @param string               $channel
     * @param string               $event
     * @param array<string, mixed> $payload
     *
     * @return void
     */
    public function to(string $channel, string $event, array $payload): void
    {
        $this->driver->publish($channel, $event, $payload);
    }
}
