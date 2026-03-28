<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Broadcast\Sse\SseEvent;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class SseEventTest
 *
 * @package Tests
 */
#[CoversClass(SseEvent::class)]
final class SseEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $event = new SseEvent('hello world', 'greet', 'id-1', 3000);

        $this->assertSame('hello world', $event->getData());
        $this->assertSame('greet', $event->getEvent());
        $this->assertSame('id-1', $event->getId());
        $this->assertSame(3000, $event->getRetry());
    }

    public function testDefaultsAreEmptyOrZero(): void
    {
        $event = new SseEvent('data only');

        $this->assertSame('data only', $event->getData());
        $this->assertSame('', $event->getEvent());
        $this->assertSame('', $event->getId());
        $this->assertSame(0, $event->getRetry());
    }

    public function testToStringWithDataOnly(): void
    {
        $event = new SseEvent('hello');

        $this->assertSame("data: hello\n\n", $event->toString());
    }

    public function testToStringWithEventName(): void
    {
        $event = new SseEvent('payload', 'update');

        $this->assertSame("event: update\ndata: payload\n\n", $event->toString());
    }

    public function testToStringWithId(): void
    {
        $event = new SseEvent('payload', '', 'msg-42');

        $this->assertSame("id: msg-42\ndata: payload\n\n", $event->toString());
    }

    public function testToStringWithRetry(): void
    {
        $event = new SseEvent('payload', '', '', 5000);

        $this->assertSame("retry: 5000\ndata: payload\n\n", $event->toString());
    }

    public function testToStringWithAllFields(): void
    {
        $event = new SseEvent('body', 'type', 'id-7', 2000);

        $expected = "id: id-7\nevent: type\nretry: 2000\ndata: body\n\n";
        $this->assertSame($expected, $event->toString());
    }

    public function testToStringWithMultilineData(): void
    {
        $event = new SseEvent("line one\nline two\nline three");

        $expected = "data: line one\ndata: line two\ndata: line three\n\n";
        $this->assertSame($expected, $event->toString());
    }

    public function testRetryZeroIsOmittedFromOutput(): void
    {
        $event = new SseEvent('data', '', '', 0);

        $this->assertStringNotContainsString('retry:', $event->toString());
    }

    public function testEmptyIdAndEventAreOmittedFromOutput(): void
    {
        $event = new SseEvent('data', '', '');

        $output = $event->toString();
        $this->assertStringNotContainsString('id:', $output);
        $this->assertStringNotContainsString('event:', $output);
    }

    public function testToStringEndsWithDoubleNewline(): void
    {
        $event = new SseEvent('data');

        $this->assertStringEndsWith("\n\n", $event->toString());
    }
}
