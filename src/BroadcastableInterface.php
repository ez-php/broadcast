<?php

declare(strict_types=1);

namespace EzPhp\Broadcast;

/**
 * Interface BroadcastableInterface
 *
 * Contract for events that can be broadcast to a named channel.
 * Implement this interface on any event class that should be pushed
 * to connected clients via the Broadcaster.
 *
 * @package EzPhp\Broadcast
 */
interface BroadcastableInterface
{
    /**
     * The channel name to broadcast on.
     *
     * @return string
     */
    public function broadcastOn(): string;

    /**
     * The event name sent to the client.
     *
     * Defaults to the class name in most implementations.
     *
     * @return string
     */
    public function broadcastAs(): string;

    /**
     * The payload to include with the broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array;
}
