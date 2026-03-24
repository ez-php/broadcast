# ez-php/broadcast

Real-time event broadcasting for ez-php applications. Publish events to named channels via a pluggable driver (Null, Log, Array) and stream them to connected clients using Server-Sent Events (SSE).

---

## Installation

```bash
composer require ez-php/broadcast
```

---

## Quick Start

Register the provider in `provider/modules.php`:

```php
use EzPhp\Broadcast\BroadcastServiceProvider;

$app->register(BroadcastServiceProvider::class);
```

Add configuration to `config/broadcast.php`:

```php
return [
    'driver'   => env('BROADCAST_DRIVER', 'null'),
    'log_path' => env('BROADCAST_LOG_PATH', ''),
];
```

Publish an event from anywhere:

```php
use EzPhp\Broadcast\Broadcast;

Broadcast::to('notifications', 'UserCreated', ['id' => 42, 'name' => 'Alice']);
```

---

## Broadcastable Events

Implement `BroadcastableInterface` to make any event class broadcastable:

```php
use EzPhp\Broadcast\BroadcastableInterface;

class UserCreated implements BroadcastableInterface
{
    public function __construct(private readonly int $userId) {}

    public function broadcastOn(): string
    {
        return 'notifications';
    }

    public function broadcastAs(): string
    {
        return 'UserCreated';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->userId];
    }
}
```

Dispatch via the facade:

```php
Broadcast::event(new UserCreated(42));
```

---

## Drivers

| Driver | `BROADCAST_DRIVER` | Description |
|--------|-------------------|-------------|
| Null   | `null`            | Silently discards all events (default) |
| Log    | `log`             | Writes a summary to a log file or via `error_log()` |
| Array  | `array`           | Stores events in memory — designed for testing |

### Log Driver

```dotenv
BROADCAST_DRIVER=log
BROADCAST_LOG_PATH=/var/www/html/storage/logs/broadcast.log
```

When `BROADCAST_LOG_PATH` is empty, events are written via `error_log()`.

### Array Driver (for Testing)

```php
use EzPhp\Broadcast\Broadcast;
use EzPhp\Broadcast\Broadcaster;
use EzPhp\Broadcast\Driver\ArrayDriver;

$driver = new ArrayDriver();
Broadcast::setBroadcaster(new Broadcaster($driver));

// ... exercise code under test ...

$events = $driver->eventsOn('notifications');
assert(count($events) === 1);
assert($events[0]['event'] === 'UserCreated');

Broadcast::resetBroadcaster();
```

---

## Server-Sent Events (SSE)

`SseEvent` and `SseStream` provide the building blocks for SSE endpoints.

### SseEvent

A single SSE frame (RFC 8895):

```php
use EzPhp\Broadcast\Sse\SseEvent;

$frame = new SseEvent(
    data:  json_encode(['id' => 42]),
    event: 'UserCreated',     // optional event type
    id:    'msg-1',           // optional ID for reconnection
    retry: 3000,              // optional reconnect interval (ms)
);

echo $frame->toString();
// id: msg-1
// event: UserCreated
// retry: 3000
// data: {"id":42}
//
```

Multi-line data is handled automatically — each newline in `$data` produces a separate `data:` line.

### SseStream

Wraps an iterable of `SseEvent` objects and provides the correct HTTP headers:

```php
use EzPhp\Broadcast\Sse\SseEvent;
use EzPhp\Broadcast\Sse\SseStream;

// In a controller:
public function stream(): void
{
    $events = $this->generateEvents(); // returns Generator<SseEvent>

    $stream = new SseStream($events);

    foreach ($stream->getHeaders() as $name => $value) {
        header("$name: $value");
    }

    $stream->stream(function (string $chunk): void {
        echo $chunk;
        ob_flush();
        flush();
    });
}
```

**Headers set by `getHeaders()`:**

| Header | Value |
|--------|-------|
| `Content-Type` | `text/event-stream` |
| `Cache-Control` | `no-cache` |
| `Connection` | `keep-alive` |
| `X-Accel-Buffering` | `no` *(disables nginx buffering)* |

---

## Static Facade

`Broadcast` is a static facade backed by a `Broadcaster` singleton:

| Method | Description |
|--------|-------------|
| `Broadcast::event(BroadcastableInterface)` | Publish via `broadcastOn()`, `broadcastAs()`, `broadcastWith()` |
| `Broadcast::to(string $channel, string $event, array $payload)` | Publish directly |
| `Broadcast::setBroadcaster(Broadcaster)` | Wire the singleton (done by `BroadcastServiceProvider`) |
| `Broadcast::resetBroadcaster()` | Reset to null — call in test `tearDown()` |

Throws `RuntimeException` if called before `setBroadcaster()`.

---

## Custom Driver

Implement `BroadcastDriverInterface` to add a custom backend:

```php
use EzPhp\Broadcast\BroadcastDriverInterface;

final class RedisPubSubDriver implements BroadcastDriverInterface
{
    public function __construct(private readonly \Redis $redis) {}

    public function publish(string $channel, string $event, array $payload): void
    {
        $this->redis->publish($channel, json_encode([
            'event'   => $event,
            'payload' => $payload,
        ]));
    }
}
```

Bind it in a service provider:

```php
$app->bind(BroadcastDriverInterface::class, fn () => new RedisPubSubDriver($redis));
```

---

## Client-Side Usage (JavaScript)

The browser's built-in `EventSource` API consumes SSE streams with no extra library required.

### Basic subscription

```javascript
const events = new EventSource('/events/notifications');

// Listen for a named event (matches the `event:` field in the SSE frame)
events.addEventListener('UserCreated', (e) => {
    const payload = JSON.parse(e.data);
    console.log('New user:', payload.id, payload.name);
});

// Listen for unnamed messages (only `data:` field, no `event:` field)
events.onmessage = (e) => {
    console.log('Message:', e.data);
};

// Handle connection errors and reconnection
events.onerror = (err) => {
    console.error('SSE error', err);
    // EventSource reconnects automatically after the `retry:` interval (default 3 s)
};

// Close the stream when no longer needed
// events.close();
```

### Authenticated streams

SSE uses standard HTTP — pass credentials via a cookie or a query-parameter token (headers are not configurable in the browser `EventSource` API):

```javascript
const token = document.querySelector('meta[name="api-token"]').content;
const events = new EventSource(`/events/notifications?token=${token}`);
```

On the PHP side, validate `$_GET['token']` in the SSE controller before opening the stream.

### Multiple event types on one connection

```javascript
const events = new EventSource('/events/feed');

['OrderPlaced', 'OrderShipped', 'OrderDelivered'].forEach((type) => {
    events.addEventListener(type, (e) => {
        const order = JSON.parse(e.data);
        updateOrderUI(order);
    });
});
```

---

## Exceptions

`BroadcastException` (extends `RuntimeException`) is the base exception for this package. `Broadcast::event()` / `Broadcast::to()` throw `RuntimeException` if called before the broadcaster is set.
