<?php

declare(strict_types=1);

namespace EzPhp\Broadcast\Sse;

/**
 * Class SseEvent
 *
 * Represents a single Server-Sent Events (SSE) frame.
 * Formats the frame according to the SSE specification (RFC 8895):
 * optional id, event name, retry interval, then one or more data lines,
 * terminated by a blank line.
 *
 * Multi-line data is handled automatically — each newline in $data
 * produces a separate "data: ..." line.
 *
 * @package EzPhp\Broadcast\Sse
 */
final class SseEvent
{
    /**
     * SseEvent Constructor
     *
     * @param string $data  The event payload (may contain newlines)
     * @param string $event Optional event type name (empty = unnamed event)
     * @param string $id    Optional event ID for reconnection tracking
     * @param int    $retry Optional reconnection time in milliseconds (0 = omit field)
     */
    public function __construct(
        private readonly string $data,
        private readonly string $event = '',
        private readonly string $id = '',
        private readonly int $retry = 0,
    ) {
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getRetry(): int
    {
        return $this->retry;
    }

    /**
     * Render the SSE frame as a string ready to be sent to the client.
     *
     * @return string
     */
    public function toString(): string
    {
        $output = '';

        if ($this->id !== '') {
            $output .= 'id: ' . $this->id . "\n";
        }

        if ($this->event !== '') {
            $output .= 'event: ' . $this->event . "\n";
        }

        if ($this->retry > 0) {
            $output .= 'retry: ' . $this->retry . "\n";
        }

        foreach (explode("\n", $this->data) as $line) {
            $output .= 'data: ' . $line . "\n";
        }

        $output .= "\n"; // blank line terminates the event

        return $output;
    }
}
