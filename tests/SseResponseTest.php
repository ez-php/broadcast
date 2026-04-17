<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Broadcast\Sse\SseEvent;
use EzPhp\Broadcast\Sse\SseResponse;
use EzPhp\Broadcast\Sse\SseStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class SseResponseTest
 *
 * @package Tests
 */
#[CoversClass(SseResponse::class)]
#[UsesClass(SseEvent::class)]
#[UsesClass(SseStream::class)]
final class SseResponseTest extends TestCase
{
    /**
     * Capture output from emit() correctly even when emit() calls ob_flush()
     * internally. Two buffer levels are opened so that ob_flush() inside emit()
     * pushes content to the outer capture buffer rather than to stdout.
     */
    private function captureEmit(SseResponse $response): string
    {
        ob_start(); // outer — captures what ob_flush() pushes up from inner
        ob_start(); // inner — emit() echoes here and ob_flush()es to outer
        $response->emit();
        ob_end_flush(); // flush any remaining inner content to outer
        $output = ob_get_clean();
        $this->assertIsString($output);

        return (string) $output;
    }

    public function testGetHeadersReturnsSseHeaders(): void
    {
        $response = new SseResponse([]);

        $headers = $response->getHeaders();

        $this->assertSame('text/event-stream', $headers['Content-Type']);
        $this->assertSame('no-cache', $headers['Cache-Control']);
        $this->assertSame('keep-alive', $headers['Connection']);
        $this->assertSame('no', $headers['X-Accel-Buffering']);
    }

    public function testEmitOutputsFormattedEvents(): void
    {
        $events = [
            new SseEvent('hello', 'greet'),
            new SseEvent('world', 'greet'),
        ];

        $output = $this->captureEmit(new SseResponse($events));

        $this->assertStringContainsString('event: greet', $output);
        $this->assertStringContainsString('data: hello', $output);
        $this->assertStringContainsString('data: world', $output);
    }

    public function testEmitWithNoEventsProducesNoOutput(): void
    {
        $this->assertSame('', $this->captureEmit(new SseResponse([])));
    }

    public function testEmitOutputsAllEventsInOrder(): void
    {
        $events = [
            new SseEvent('first'),
            new SseEvent('second'),
            new SseEvent('third'),
        ];

        $output = $this->captureEmit(new SseResponse($events));

        $firstPos = strpos($output, 'data: first');
        $secondPos = strpos($output, 'data: second');
        $thirdPos = strpos($output, 'data: third');

        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertNotFalse($thirdPos);
        $this->assertLessThan($secondPos, $firstPos);
        $this->assertLessThan($thirdPos, $secondPos);
    }

    public function testEmitWorksWithGeneratorAsIterable(): void
    {
        $generator = (static function (): \Generator {
            yield new SseEvent('tick', 'heartbeat');
        })();

        $output = $this->captureEmit(new SseResponse($generator));

        $this->assertStringContainsString('event: heartbeat', $output);
        $this->assertStringContainsString('data: tick', $output);
    }
}
