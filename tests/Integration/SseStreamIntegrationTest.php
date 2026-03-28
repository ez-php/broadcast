<?php

declare(strict_types=1);

namespace Tests\Integration;

use EzPhp\Broadcast\Broadcast;
use EzPhp\Broadcast\BroadcastableInterface;
use EzPhp\Broadcast\Broadcaster;
use EzPhp\Broadcast\Driver\ArrayDriver;
use EzPhp\Broadcast\Driver\NullDriver;
use EzPhp\Broadcast\Sse\SseEvent;
use EzPhp\Broadcast\Sse\SseStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Broadcastable event stub used across multiple test cases.
 * Named class (not anonymous) keeps PHPStan happy at level 9.
 */
final class UserCreatedEvent implements BroadcastableInterface
{
    /**
     * @param int    $userId
     * @param string $email
     */
    public function __construct(
        private readonly int $userId,
        private readonly string $email,
    ) {
    }

    /**
     * @return string
     */
    public function broadcastOn(): string
    {
        return 'users';
    }

    /**
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'UserCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->userId, 'email' => $this->email];
    }
}

/**
 * Integration tests for the ez-php/broadcast SSE stream and driver handoff.
 *
 * These tests verify the full pipeline from broadcasting an event through a
 * driver all the way to formatted SSE frames delivered to a client sink.
 *
 * Scenarios covered:
 *   - Broadcaster → ArrayDriver → SseEvent frames → SseStream output
 *   - SseStream HTTP headers are correct for SSE clients
 *   - Broadcast facade routes events to the configured driver
 *   - Driver handoff: switching Broadcaster mid-session routes subsequent events
 *     to the new driver while old events stay in the previous driver
 *   - Switching from NullDriver to ArrayDriver captures only post-handoff events
 *   - Generator-based SseStream iterates events lazily
 *   - Multiple channels are isolated inside ArrayDriver
 */
#[CoversClass(Broadcaster::class)]
#[CoversClass(SseStream::class)]
#[UsesClass(Broadcast::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(NullDriver::class)]
#[UsesClass(SseEvent::class)]
final class SseStreamIntegrationTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        Broadcast::resetBroadcaster();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Broadcast::resetBroadcaster();
    }

    // ─── SSE stream from broadcast events ────────────────────────────────────

    /**
     * Events broadcast via Broadcaster end up in ArrayDriver and can be
     * converted to SSE frames that contain the correct event name and payload.
     *
     * @return void
     */
    public function testBroadcastEventsAreFormattedAsSSEFrames(): void
    {
        $driver = new ArrayDriver();
        $broadcaster = new Broadcaster($driver);

        $broadcaster->to('orders', 'OrderPlaced', ['id' => 1, 'total' => 99.99]);
        $broadcaster->to('orders', 'OrderShipped', ['id' => 1, 'tracking' => 'ABC123']);

        $events = $driver->eventsOn('orders');

        $sseEvents = array_map(
            static fn (array $e): SseEvent => new SseEvent(
                json_encode($e['payload'], JSON_THROW_ON_ERROR),
                $e['event'],
            ),
            $events,
        );

        $stream = new SseStream($sseEvents);
        $chunks = [];
        $stream->stream(static function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        });

        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('event: OrderPlaced', $chunks[0]);
        $this->assertStringContainsString('"id":1', $chunks[0]);
        $this->assertStringContainsString('event: OrderShipped', $chunks[1]);
        $this->assertStringContainsString('ABC123', $chunks[1]);
    }

    /**
     * SseStream must return the four HTTP headers required by the SSE specification.
     *
     * @return void
     */
    public function testSseStreamHeadersAreCorrectForEventStream(): void
    {
        $stream = new SseStream([]);
        $headers = $stream->getHeaders();

        $this->assertSame('text/event-stream', $headers['Content-Type']);
        $this->assertSame('no-cache', $headers['Cache-Control']);
        $this->assertSame('keep-alive', $headers['Connection']);
        $this->assertSame('no', $headers['X-Accel-Buffering']);
    }

    /**
     * Events published via the static Broadcast facade reach the ArrayDriver
     * when a Broadcaster backed by ArrayDriver is configured.
     *
     * @return void
     */
    public function testBroadcastViaFacadeReachesArrayDriver(): void
    {
        $driver = new ArrayDriver();
        Broadcast::setBroadcaster(new Broadcaster($driver));

        Broadcast::event(new UserCreatedEvent(42, 'alice@example.com'));

        $events = $driver->eventsOn('users');
        $this->assertCount(1, $events);
        $this->assertSame('UserCreated', $events[0]['event']);
        $this->assertSame(42, $events[0]['payload']['id']);
        $this->assertSame('alice@example.com', $events[0]['payload']['email']);
    }

    // ─── Driver handoff ───────────────────────────────────────────────────────

    /**
     * Replacing the Broadcaster (driver handoff) routes subsequent events to the
     * new driver. Events already captured by the old driver are not affected.
     *
     * @return void
     */
    public function testDriverHandoffRoutesEventsToNewDriver(): void
    {
        $driver1 = new ArrayDriver();
        $driver2 = new ArrayDriver();

        Broadcast::setBroadcaster(new Broadcaster($driver1));
        Broadcast::to('ch', 'First', ['n' => 1]);

        // Handoff to driver2
        Broadcast::setBroadcaster(new Broadcaster($driver2));
        Broadcast::to('ch', 'Second', ['n' => 2]);

        $this->assertCount(1, $driver1->eventsOn('ch'));
        $this->assertSame('First', $driver1->eventsOn('ch')[0]['event']);

        $this->assertCount(1, $driver2->eventsOn('ch'));
        $this->assertSame('Second', $driver2->eventsOn('ch')[0]['event']);
    }

    /**
     * After switching from NullDriver to ArrayDriver, only events published
     * after the handoff are captured — NullDriver silently discarded the earlier ones.
     *
     * @return void
     */
    public function testSwitchingFromNullToArrayDriverCapturesOnlySubsequentEvents(): void
    {
        Broadcast::setBroadcaster(new Broadcaster(new NullDriver()));
        Broadcast::to('ch', 'Discarded', []);

        $arrayDriver = new ArrayDriver();
        Broadcast::setBroadcaster(new Broadcaster($arrayDriver));
        Broadcast::to('ch', 'Captured', ['key' => 'value']);

        $events = $arrayDriver->eventsOn('ch');
        $this->assertCount(1, $events);
        $this->assertSame('Captured', $events[0]['event']);
    }

    // ─── Generator-based streaming ────────────────────────────────────────────

    /**
     * SseStream accepts a generator — events from the broadcast driver are
     * lazily converted and each frame is passed to the sink in order.
     *
     * @return void
     */
    public function testSseStreamWithGeneratorFromBroadcastEvents(): void
    {
        $driver = new ArrayDriver();
        $broadcaster = new Broadcaster($driver);

        $broadcaster->to('live', 'Score', ['home' => 1, 'away' => 0]);
        $broadcaster->to('live', 'Score', ['home' => 2, 'away' => 0]);
        $broadcaster->to('live', 'FinalWhistle', []);

        $events = $driver->eventsOn('live');

        $generator = (static function (array $events): \Generator {
            foreach ($events as $e) {
                yield new SseEvent(
                    json_encode($e['payload'], JSON_THROW_ON_ERROR),
                    $e['event'],
                );
            }
        })($events);

        $stream = new SseStream($generator);
        $output = '';
        $stream->stream(static function (string $chunk) use (&$output): void {
            $output .= $chunk;
        });

        $this->assertStringContainsString('event: Score', $output);
        $this->assertStringContainsString('event: FinalWhistle', $output);
        // Three events → three blank-line terminators
        $this->assertSame(3, substr_count($output, "\n\n"));
    }

    // ─── Channel isolation ────────────────────────────────────────────────────

    /**
     * ArrayDriver isolates events by channel — events on one channel do not
     * bleed into another.
     *
     * @return void
     */
    public function testMultipleChannelsAreIsolatedInArrayDriver(): void
    {
        $driver = new ArrayDriver();
        $broadcaster = new Broadcaster($driver);

        $broadcaster->to('channel-a', 'EvA', ['x' => 1]);
        $broadcaster->to('channel-b', 'EvB', ['y' => 2]);
        $broadcaster->to('channel-a', 'EvA2', ['x' => 3]);

        $this->assertCount(2, $driver->eventsOn('channel-a'));
        $this->assertCount(1, $driver->eventsOn('channel-b'));

        // SSE output per channel is independent
        $frameCount = 0;
        $stream = new SseStream(
            array_map(
                static fn (array $e): SseEvent => new SseEvent(
                    json_encode($e['payload'], JSON_THROW_ON_ERROR),
                    $e['event'],
                ),
                $driver->eventsOn('channel-a'),
            ),
        );
        $stream->stream(static function (string $chunk) use (&$frameCount): void {
            $frameCount++;
        });

        $this->assertSame(2, $frameCount, 'Only channel-a events should be streamed');
    }
}
