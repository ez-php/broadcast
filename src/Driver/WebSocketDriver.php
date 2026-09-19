<?php

declare(strict_types=1);

namespace EzPhp\Broadcast\Driver;

use EzPhp\Broadcast\BroadcastDriverInterface;
use EzPhp\WebSocket\ChannelManager;

/**
 * Class WebSocketDriver
 *
 * Publishes broadcast events directly to a same-process `ez-php/websocket`
 * `ChannelManager`, for applications running their WebSocket server and
 * broadcast producer in the same PHP process (e.g. a handler that both
 * serves WebSocket connections and reacts to application events). Delivery
 * is synchronous and in-memory — there is no separate subscriber gateway to
 * run, unlike `RedisDriver`.
 *
 * `ez-php/websocket` is a require-dev-only (soft) dependency of this
 * package — PSR-4 resolves this class only when something actually
 * references it, so it ships in `src/` without pulling `ez-php/websocket`
 * into an install that never uses it (same reasoning as `ez-php/mail`'s
 * `Job\SendMailableJob`).
 *
 * @package EzPhp\Broadcast\Driver
 */
final class WebSocketDriver implements BroadcastDriverInterface
{
    /**
     * @param ChannelManager $channels
     */
    public function __construct(private readonly ChannelManager $channels)
    {
    }

    /**
     * Publish an event to every connection subscribed to the given channel.
     *
     * The message is a JSON object with "event" and "payload" keys, matching
     * `RedisDriver`'s wire format so client-side handlers can share decoding
     * logic across drivers.
     *
     * @param string               $channel The target channel name
     * @param string               $event   The event name
     * @param array<string, mixed> $payload Arbitrary event payload
     *
     * @return void
     */
    public function publish(string $channel, string $event, array $payload): void
    {
        $message = json_encode(
            ['event' => $event, 'payload' => $payload],
            JSON_THROW_ON_ERROR,
        );

        $this->channels->broadcast($channel, $message);
    }
}
