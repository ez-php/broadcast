<?php

declare(strict_types=1);

namespace Tests\Broadcast\Driver;

use EzPhp\Broadcast\Driver\WebSocketDriver;
use EzPhp\WebSocket\ChannelManager;
use EzPhp\WebSocket\ConnectionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class WebSocketDriverTest
 *
 * Uses a real EzPhp\WebSocket\ChannelManager with an in-memory fake
 * connection — no socket/network I/O needed to verify the driver delegates
 * correctly. ChannelManager is not covered by #[UsesClass] since it belongs
 * to ez-php/websocket, outside this module's coverage source.
 *
 * @package Tests\Broadcast\Driver
 */
#[CoversClass(WebSocketDriver::class)]
final class WebSocketDriverTest extends TestCase
{
    /**
     * @return ConnectionInterface&object{sent: list<string>}
     */
    private function fakeConnection(string $id): ConnectionInterface
    {
        return new class ($id) implements ConnectionInterface {
            /** @var list<string> */
            public array $sent = [];

            public function __construct(private readonly string $connId)
            {
            }

            public function id(): string
            {
                return $this->connId;
            }

            public function isConnected(): bool
            {
                return true;
            }

            public function send(string $data): void
            {
                $this->sent[] = $data;
            }

            public function sendBinary(string $data): void
            {
            }

            public function close(?string $reason = null): void
            {
            }

            /**
             * @return array<string, string>
             */
            public function requestHeaders(): array
            {
                return [];
            }

            public function header(string $name): ?string
            {
                return null;
            }
        };
    }

    public function testPublishBroadcastsToAllSubscribersOfTheChannel(): void
    {
        $channels = new ChannelManager();
        $conn = $this->fakeConnection('conn-1');
        $channels->subscribe('room-1', $conn);

        $driver = new WebSocketDriver($channels);
        $driver->publish('room-1', 'UserJoined', ['id' => 1]);

        $this->assertCount(1, $conn->sent);
    }

    public function testPublishEncodesEventAndPayloadAsJson(): void
    {
        $channels = new ChannelManager();
        $conn = $this->fakeConnection('conn-1');
        $channels->subscribe('room-1', $conn);

        $driver = new WebSocketDriver($channels);
        $driver->publish('room-1', 'ScoreUpdated', ['score' => 42]);

        $decoded = json_decode($conn->sent[0], true);
        $this->assertIsArray($decoded);
        $this->assertSame('ScoreUpdated', $decoded['event']);
        $this->assertSame(['score' => 42], $decoded['payload']);
    }

    public function testPublishToChannelWithNoSubscribersIsANoop(): void
    {
        $channels = new ChannelManager();
        $driver = new WebSocketDriver($channels);

        $driver->publish('empty-room', 'Ev', []);

        $this->addToAssertionCount(1);
    }

    public function testPublishOnlyReachesSubscribersOfTheGivenChannel(): void
    {
        $channels = new ChannelManager();
        $connA = $this->fakeConnection('conn-a');
        $connB = $this->fakeConnection('conn-b');
        $channels->subscribe('room-a', $connA);
        $channels->subscribe('room-b', $connB);

        $driver = new WebSocketDriver($channels);
        $driver->publish('room-a', 'Ev', []);

        $this->assertCount(1, $connA->sent);
        $this->assertCount(0, $connB->sent);
    }
}
