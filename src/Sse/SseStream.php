<?php

declare(strict_types=1);

namespace EzPhp\Broadcast\Sse;

/**
 * Class SseStream
 *
 * Wraps an iterable of SseEvent objects and provides the correct HTTP headers
 * for a Server-Sent Events response. Call stream() with a sink closure to
 * iterate the events and push each formatted frame.
 *
 * Typical controller usage:
 *
 *   $stream = new SseStream($this->generator());
 *   foreach ($stream->getHeaders() as $name => $value) {
 *       header("$name: $value");
 *   }
 *   $stream->stream(function (string $chunk): void {
 *       echo $chunk;
 *       ob_flush();
 *       flush();
 *   });
 *
 * @package EzPhp\Broadcast\Sse
 */
final class SseStream
{
    /**
     * SseStream Constructor
     *
     * @param iterable<SseEvent> $events
     */
    public function __construct(private readonly iterable $events)
    {
    }

    /**
     * Return the HTTP headers required for a Server-Sent Events response.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    /**
     * Iterate the events and pass each formatted SSE frame to the sink closure.
     *
     * @param \Closure(string): void $send
     *
     * @return void
     */
    public function stream(\Closure $send): void
    {
        foreach ($this->events as $event) {
            $send($event->toString());
        }
    }
}
