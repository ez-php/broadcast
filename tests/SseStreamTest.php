<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Broadcast\Sse\SseEvent;
use EzPhp\Broadcast\Sse\SseStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class SseStreamTest
 *
 * @package Tests
 */
#[CoversClass(SseStream::class)]
#[UsesClass(SseEvent::class)]
final class SseStreamTest extends TestCase
{
    public function testGetHeadersReturnsCorrectContentType(): void
    {
        $stream = new SseStream([]);

        $headers = $stream->getHeaders();

        $this->assertSame('text/event-stream', $headers['Content-Type']);
    }

    public function testGetHeadersReturnsNoCacheControl(): void
    {
        $stream = new SseStream([]);

        $this->assertSame('no-cache', $stream->getHeaders()['Cache-Control']);
    }

    public function testGetHeadersReturnsKeepAliveConnection(): void
    {
        $stream = new SseStream([]);

        $this->assertSame('keep-alive', $stream->getHeaders()['Connection']);
    }

    public function testGetHeadersDisablesNginxBuffering(): void
    {
        $stream = new SseStream([]);

        $this->assertSame('no', $stream->getHeaders()['X-Accel-Buffering']);
    }

    public function testStreamCallsSendForEachEvent(): void
    {
        $events = [
            new SseEvent('first'),
            new SseEvent('second'),
        ];

        $stream = new SseStream($events);

        $sent = [];
        $stream->stream(function (string $chunk) use (&$sent): void {
            $sent[] = $chunk;
        });

        $this->assertCount(2, $sent);
        $this->assertSame("data: first\n\n", $sent[0]);
        $this->assertSame("data: second\n\n", $sent[1]);
    }

    public function testStreamWithEmptyIterableCallsSendZeroTimes(): void
    {
        $stream = new SseStream([]);

        $count = 0;
        $stream->stream(function (string $chunk) use (&$count): void {
            $count++;
        });

        $this->assertSame(0, $count);
    }

    public function testStreamWorksWithGenerator(): void
    {
        $generator = (static function (): \Generator {
            yield new SseEvent('from-generator', 'gen-event');
        })();

        $stream = new SseStream($generator);

        $sent = [];
        $stream->stream(function (string $chunk) use (&$sent): void {
            $sent[] = $chunk;
        });

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('event: gen-event', $sent[0]);
        $this->assertStringContainsString('data: from-generator', $sent[0]);
    }

    public function testStreamPreservesEventOrder(): void
    {
        $events = [
            new SseEvent('a', 'first'),
            new SseEvent('b', 'second'),
            new SseEvent('c', 'third'),
        ];

        $stream = new SseStream($events);

        $names = [];
        $stream->stream(function (string $chunk) use (&$names): void {
            if (preg_match('/event: (\S+)/', $chunk, $m)) {
                $names[] = $m[1];
            }
        });

        $this->assertSame(['first', 'second', 'third'], $names);
    }
}
