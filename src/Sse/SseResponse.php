<?php

declare(strict_types=1);

namespace EzPhp\Broadcast\Sse;

/**
 * Class SseResponse
 *
 * Convenience wrapper that combines SseStream with HTTP header output and
 * output-buffer flushing into a single emit() call.
 *
 * Typical controller usage:
 *
 *   $response = new SseResponse($this->eventGenerator());
 *   $response->emit();
 *   exit;
 *
 * The constructor accepts any iterable of SseEvent objects, including
 * generators (for lazy / infinite streams).
 *
 * @package EzPhp\Broadcast\Sse
 */
final class SseResponse
{
    private readonly SseStream $stream;

    /**
     * SseResponse Constructor
     *
     * @param iterable<SseEvent> $events
     */
    public function __construct(iterable $events)
    {
        $this->stream = new SseStream($events);
    }

    /**
     * Return the HTTP headers required for a Server-Sent Events response.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->stream->getHeaders();
    }

    /**
     * Send SSE headers, then stream all events to the client.
     *
     * Each event frame is echoed immediately and the output buffer is flushed
     * after every frame so that clients receive events as they are produced.
     *
     * @return void
     */
    public function emit(): void
    {
        foreach ($this->stream->getHeaders() as $name => $value) {
            header("{$name}: {$value}");
        }

        $this->stream->stream(static function (string $chunk): void {
            echo $chunk;

            if (ob_get_level() > 0) {
                ob_flush();
            }

            flush();
        });
    }
}
