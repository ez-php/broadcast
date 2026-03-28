<?php

declare(strict_types=1);

namespace EzPhp\Broadcast;

use EzPhp\Broadcast\Driver\ArrayDriver;
use EzPhp\Broadcast\Driver\LogDriver;
use EzPhp\Broadcast\Driver\NullDriver;
use EzPhp\Broadcast\Driver\RedisDriver;
use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;

/**
 * Class BroadcastServiceProvider
 *
 * Binds the BroadcastDriverInterface and Broadcaster to the container,
 * then wires the Broadcast static facade in boot().
 *
 * Configuration keys (in config/broadcast.php or environment):
 *   - broadcast.driver         — "null" (default) | "log" | "array" | "redis"
 *   - broadcast.log_path       — absolute path for the log driver (empty = error_log())
 *   - broadcast.redis.host     — Redis hostname (default: "127.0.0.1")
 *   - broadcast.redis.port     — Redis port (default: 6379)
 *   - broadcast.redis.database — Redis database index (default: 0)
 *
 * @package EzPhp\Broadcast
 */
final class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(
            BroadcastDriverInterface::class,
            function (ContainerInterface $app): BroadcastDriverInterface {
                $config = $app->make(ConfigInterface::class);
                $raw = $config->get('broadcast.driver', 'null');
                $driver = is_string($raw) ? $raw : 'null';

                return match ($driver) {
                    'log' => new LogDriver($this->resolveLogPath($config)),
                    'array' => new ArrayDriver(),
                    'redis' => $this->createRedisDriver($config),
                    default => new NullDriver(),
                };
            }
        );

        $this->app->bind(
            Broadcaster::class,
            function (ContainerInterface $app): Broadcaster {
                return new Broadcaster($app->make(BroadcastDriverInterface::class));
            }
        );
    }

    /**
     * Resolve the log path from config, falling back to empty string.
     *
     * @param ConfigInterface $config
     *
     * @return string
     */
    private function resolveLogPath(ConfigInterface $config): string
    {
        $path = $config->get('broadcast.log_path', '');

        return is_string($path) ? $path : '';
    }

    /**
     * Create a RedisDriver from config values.
     *
     * @param ConfigInterface $config
     *
     * @return RedisDriver
     */
    private function createRedisDriver(ConfigInterface $config): RedisDriver
    {
        $host = $config->get('broadcast.redis.host', '127.0.0.1');
        $port = $config->get('broadcast.redis.port', 6379);
        $db = $config->get('broadcast.redis.database', 0);

        return new RedisDriver(
            host: is_string($host) ? $host : '127.0.0.1',
            port: is_int($port) ? $port : 6379,
            database: is_int($db) ? $db : 0,
        );
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        Broadcast::setBroadcaster($this->app->make(Broadcaster::class));
    }
}
