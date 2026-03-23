<?php

declare(strict_types=1);

namespace EzPhp\Broadcast;

use EzPhp\Broadcast\Driver\ArrayDriver;
use EzPhp\Broadcast\Driver\LogDriver;
use EzPhp\Broadcast\Driver\NullDriver;
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
 *   - broadcast.driver   — "null" (default) | "log" | "array"
 *   - broadcast.log_path — absolute path for the log driver (empty = error_log())
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
                $driver = (string) $config->get('broadcast.driver', 'null');

                return match ($driver) {
                    'log' => new LogDriver((string) $config->get('broadcast.log_path', '')),
                    'array' => new ArrayDriver(),
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
     * @return void
     */
    public function boot(): void
    {
        Broadcast::setBroadcaster($this->app->make(Broadcaster::class));
    }
}
