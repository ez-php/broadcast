<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Broadcast\BroadcastableInterface;
use EzPhp\Broadcast\BroadcastDriverInterface;
use EzPhp\Broadcast\Broadcaster;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class BroadcasterTest
 *
 * @package Tests
 */
#[CoversClass(Broadcaster::class)]
final class BroadcasterTest extends TestCase
{
    public function testEventPublishesToDriverWithCorrectArguments(): void
    {
        $driver = new class () implements BroadcastDriverInterface {
            /** @var list<array{channel: string, event: string, payload: array<string, mixed>}> */
            public array $calls = [];

            /** @param array<string, mixed> $payload */
            public function publish(string $channel, string $event, array $payload): void
            {
                $this->calls[] = ['channel' => $channel, 'event' => $event, 'payload' => $payload];
            }
        };

        $broadcaster = new Broadcaster($driver);

        $broadcastable = new class () implements BroadcastableInterface {
            public function broadcastOn(): string
            {
                return 'test-channel';
            }

            public function broadcastAs(): string
            {
                return 'TestEvent';
            }

            /** @return array<string, mixed> */
            public function broadcastWith(): array
            {
                return ['key' => 'value'];
            }
        };

        $broadcaster->event($broadcastable);

        $this->assertCount(1, $driver->calls);
        $this->assertSame('test-channel', $driver->calls[0]['channel']);
        $this->assertSame('TestEvent', $driver->calls[0]['event']);
        $this->assertSame(['key' => 'value'], $driver->calls[0]['payload']);
    }

    public function testEventWithEmptyPayload(): void
    {
        $driver = new class () implements BroadcastDriverInterface {
            /** @var list<array{channel: string, event: string, payload: array<string, mixed>}> */
            public array $calls = [];

            /** @param array<string, mixed> $payload */
            public function publish(string $channel, string $event, array $payload): void
            {
                $this->calls[] = ['channel' => $channel, 'event' => $event, 'payload' => $payload];
            }
        };

        $broadcaster = new Broadcaster($driver);

        $broadcastable = new class () implements BroadcastableInterface {
            public function broadcastOn(): string
            {
                return 'ch';
            }

            public function broadcastAs(): string
            {
                return 'Ev';
            }

            /** @return array<string, mixed> */
            public function broadcastWith(): array
            {
                return [];
            }
        };

        $broadcaster->event($broadcastable);

        $this->assertSame([], $driver->calls[0]['payload']);
    }

    public function testToPublishesDirectlyWithGivenArguments(): void
    {
        $driver = new class () implements BroadcastDriverInterface {
            /** @var list<array{channel: string, event: string, payload: array<string, mixed>}> */
            public array $calls = [];

            /** @param array<string, mixed> $payload */
            public function publish(string $channel, string $event, array $payload): void
            {
                $this->calls[] = ['channel' => $channel, 'event' => $event, 'payload' => $payload];
            }
        };

        $broadcaster = new Broadcaster($driver);
        $broadcaster->to('my-channel', 'my-event', ['x' => 1]);

        $this->assertCount(1, $driver->calls);
        $this->assertSame('my-channel', $driver->calls[0]['channel']);
        $this->assertSame('my-event', $driver->calls[0]['event']);
        $this->assertSame(['x' => 1], $driver->calls[0]['payload']);
    }

    public function testMultiplePublishesAccumulate(): void
    {
        $driver = new class () implements BroadcastDriverInterface {
            /** @var list<array{channel: string, event: string, payload: array<string, mixed>}> */
            public array $calls = [];

            /** @param array<string, mixed> $payload */
            public function publish(string $channel, string $event, array $payload): void
            {
                $this->calls[] = ['channel' => $channel, 'event' => $event, 'payload' => $payload];
            }
        };

        $broadcaster = new Broadcaster($driver);
        $broadcaster->to('a', 'ev1', []);
        $broadcaster->to('b', 'ev2', []);

        $this->assertCount(2, $driver->calls);
        $this->assertSame('a', $driver->calls[0]['channel']);
        $this->assertSame('b', $driver->calls[1]['channel']);
    }
}
