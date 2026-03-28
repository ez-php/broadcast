<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Broadcast\Broadcast;
use EzPhp\Broadcast\BroadcastableInterface;
use EzPhp\Broadcast\BroadcastDriverInterface;
use EzPhp\Broadcast\Broadcaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class BroadcastTest
 *
 * @package Tests
 */
#[CoversClass(Broadcast::class)]
#[UsesClass(Broadcaster::class)]
final class BroadcastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Broadcast::resetBroadcaster();
    }

    protected function tearDown(): void
    {
        Broadcast::resetBroadcaster();
        parent::tearDown();
    }

    public function testThrowsWhenBroadcasterNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Broadcaster not set/');

        Broadcast::to('ch', 'ev', []);
    }

    public function testThrowsOnEventWhenBroadcasterNotSet(): void
    {
        $this->expectException(\RuntimeException::class);

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

        Broadcast::event($broadcastable);
    }

    public function testSetBroadcasterAllowsSubsequentCalls(): void
    {
        $driver = new class () implements BroadcastDriverInterface {
            public bool $called = false;

            /** @param array<string, mixed> $payload */
            public function publish(string $channel, string $event, array $payload): void
            {
                $this->called = true;
            }
        };

        Broadcast::setBroadcaster(new Broadcaster($driver));
        Broadcast::to('ch', 'ev', []);

        $this->assertTrue($driver->called);
    }

    public function testEventDelegatesToBroadcaster(): void
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

        Broadcast::setBroadcaster(new Broadcaster($driver));

        $broadcastable = new class () implements BroadcastableInterface {
            public function broadcastOn(): string
            {
                return 'notifications';
            }

            public function broadcastAs(): string
            {
                return 'UserCreated';
            }

            /** @return array<string, mixed> */
            public function broadcastWith(): array
            {
                return ['id' => 42];
            }
        };

        Broadcast::event($broadcastable);

        $this->assertCount(1, $driver->calls);
        $this->assertSame('notifications', $driver->calls[0]['channel']);
        $this->assertSame('UserCreated', $driver->calls[0]['event']);
        $this->assertSame(['id' => 42], $driver->calls[0]['payload']);
    }

    public function testToDelegatesToBroadcaster(): void
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

        Broadcast::setBroadcaster(new Broadcaster($driver));
        Broadcast::to('c', 'e', ['k' => 'v']);

        $this->assertSame('c', $driver->calls[0]['channel']);
        $this->assertSame('e', $driver->calls[0]['event']);
        $this->assertSame(['k' => 'v'], $driver->calls[0]['payload']);
    }

    public function testResetCausesThrowOnNextCall(): void
    {
        Broadcast::setBroadcaster(new Broadcaster(new class () implements BroadcastDriverInterface {
            /** @param array<string, mixed> $payload */
            public function publish(string $channel, string $event, array $payload): void
            {
            }
        }));

        Broadcast::resetBroadcaster();

        $this->expectException(\RuntimeException::class);
        Broadcast::to('ch', 'ev', []);
    }
}
