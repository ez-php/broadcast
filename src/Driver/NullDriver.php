<?php

declare(strict_types=1);

namespace EzPhp\Broadcast\Driver;

use EzPhp\Broadcast\BroadcastDriverInterface;

/**
 * Class NullDriver
 *
 * Silently discards all published events. Use as the default driver in
 * production when no real broadcast backend is configured, or in tests
 * where broadcast side-effects are irrelevant.
 *
 * @package EzPhp\Broadcast\Driver
 */
final class NullDriver implements BroadcastDriverInterface
{
    /**
     * @param string               $channel
     * @param string               $event
     * @param array<string, mixed> $payload
     *
     * @return void
     */
    public function publish(string $channel, string $event, array $payload): void
    {
        // intentional no-op
    }
}
