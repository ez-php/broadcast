<?php

declare(strict_types=1);

namespace EzPhp\Broadcast;

/**
 * Class Broadcast
 *
 * Static facade for the Broadcaster singleton.
 * Call setBroadcaster() (done automatically by BroadcastServiceProvider)
 * before using any static methods. Throws RuntimeException if called before
 * the broadcaster is set — fail-fast prevents silent discards.
 *
 * Global state is intentional and documented — the facade allows
 * Broadcast::event() from anywhere without container access.
 *
 * @package EzPhp\Broadcast
 */
final class Broadcast
{
    /**
     * @var Broadcaster|null
     */
    private static ?Broadcaster $broadcaster = null;

    /**
     * Set the underlying Broadcaster instance.
     *
     * @param Broadcaster $broadcaster
     *
     * @return void
     */
    public static function setBroadcaster(Broadcaster $broadcaster): void
    {
        self::$broadcaster = $broadcaster;
    }

    /**
     * Reset the Broadcaster singleton to null.
     *
     * Call this in setUp()/tearDown() of any test that touches the Broadcast facade.
     *
     * @return void
     */
    public static function resetBroadcaster(): void
    {
        self::$broadcaster = null;
    }

    /**
     * Publish a broadcastable event.
     *
     * @param BroadcastableInterface $event
     *
     * @return void
     */
    public static function event(BroadcastableInterface $event): void
    {
        self::broadcaster()->event($event);
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
    public static function to(string $channel, string $event, array $payload): void
    {
        self::broadcaster()->to($channel, $event, $payload);
    }

    /**
     * Return the current Broadcaster instance or throw if not set.
     *
     * @return Broadcaster
     */
    private static function broadcaster(): Broadcaster
    {
        if (self::$broadcaster === null) {
            throw new \RuntimeException(
                'Broadcaster not set. Register BroadcastServiceProvider or call Broadcast::setBroadcaster().'
            );
        }

        return self::$broadcaster;
    }
}
