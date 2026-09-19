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
     * @param BroadcastDriverInterface        $driver
     * @param ChannelAuthorizerInterface|null $authorizer Optional access-control hook checked
     *                                                     before every publish; null allows everything.
     */
    public function __construct(
        private readonly BroadcastDriverInterface $driver,
        private readonly ?ChannelAuthorizerInterface $authorizer = null,
    ) {
    }

    /**
     * Publish a broadcastable event using its own channel, name, and payload.
     *
     * @param BroadcastableInterface $event
     *
     * @throws BroadcastException When a configured authorizer denies the channel.
     *
     * @return void
     */
    public function event(BroadcastableInterface $event): void
    {
        $this->to($event->broadcastOn(), $event->broadcastAs(), $event->broadcastWith());
    }

    /**
     * Publish an event directly to the given channel.
     *
     * @param string               $channel
     * @param string               $event
     * @param array<string, mixed> $payload
     *
     * @throws BroadcastException When a configured authorizer denies the channel.
     *
     * @return void
     */
    public function to(string $channel, string $event, array $payload): void
    {
        if ($this->authorizer !== null && !$this->authorizer->authorize($channel)) {
            throw new BroadcastException("Channel '{$channel}' is not authorized for publishing.");
        }

        $this->driver->publish($channel, $event, $payload);
    }
}
