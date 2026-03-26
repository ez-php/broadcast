<?php

declare(strict_types=1);

namespace EzPhp\Broadcast\Driver;

use EzPhp\Broadcast\BroadcastDriverInterface;
use Redis;
use RuntimeException;

/**
 * Class RedisDriver
 *
 * Publishes broadcast events to Redis Pub/Sub channels using the native
 * ext-redis extension. Each call to publish() serialises the event name
 * and payload as JSON and calls Redis::publish() on the given channel.
 *
 * Subscribers (SSE proxy, WebSocket server, etc.) must subscribe to the
 * same Redis channel to receive messages.
 *
 * @package EzPhp\Broadcast\Driver
 */
final class RedisDriver implements BroadcastDriverInterface
{
    private readonly Redis $redis;

    /**
     * RedisDriver Constructor
     *
     * @param string $host     Redis hostname
     * @param int    $port     Redis port
     * @param int    $database Redis database index (0–15)
     *
     * @throws RuntimeException if ext-redis is not loaded
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        int $database = 0,
    ) {
        if (!extension_loaded('redis')) {
            throw new RuntimeException(
                'The ext-redis extension is required to use RedisDriver.'
            );
        }

        $this->redis = new Redis();
        $this->redis->connect($host, $port);

        if ($database !== 0) {
            $this->redis->select($database);
        }
    }

    /**
     * Publish an event to the given Redis Pub/Sub channel.
     *
     * The message is a JSON object with "event" and "payload" keys.
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

        $this->redis->publish($channel, $message);
    }
}
