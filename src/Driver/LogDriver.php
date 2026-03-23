<?php

declare(strict_types=1);

namespace EzPhp\Broadcast\Driver;

use EzPhp\Broadcast\BroadcastDriverInterface;

/**
 * Class LogDriver
 *
 * Writes a one-line summary of each published event to a log file.
 * The log directory is created on demand. When logPath is empty,
 * output goes via error_log() — suitable for local development and CI.
 *
 * @package EzPhp\Broadcast\Driver
 */
final class LogDriver implements BroadcastDriverInterface
{
    /**
     * LogDriver Constructor
     *
     * @param string $logPath Absolute path to the log file, or empty to use error_log()
     */
    public function __construct(private readonly string $logPath = '')
    {
    }

    /**
     * @param string               $channel
     * @param string               $event
     * @param array<string, mixed> $payload
     *
     * @return void
     */
    public function publish(string $channel, string $event, array $payload): void
    {
        $line = sprintf(
            '[%s] channel=%s event=%s payload=%s',
            date('Y-m-d H:i:s'),
            $channel,
            $event,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        if ($this->logPath !== '') {
            $dir = dirname($this->logPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }

            file_put_contents($this->logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }
}
