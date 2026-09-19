# ez-php/broadcast

Real-time event broadcasting for ez-php applications. Publish events to named channels via a pluggable driver (Null, Log, Array, Redis) and stream them to connected clients using Server-Sent Events (SSE).

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
| Redis  | `redis`           | Publishes to Redis Pub/Sub channels via `ext-redis` |
| WebSocket | *(manual wiring, not config-driven — see below)* | Publishes directly to a same-process `ez-php/websocket` `ChannelManager` |

### Log Driver

```dotenv
BROADCAST_DRIVER=log
BROADCAST_LOG_PATH=/var/www/html/storage/logs/broadcast.log
```

When `BROADCAST_LOG_PATH` is empty, events are written via `error_log()`.

### Redis Driver

```dotenv
BROADCAST_DRIVER=redis
BROADCAST_REDIS_HOST=redis
BROADCAST_REDIS_PORT=6379
BROADCAST_REDIS_DATABASE=0
```

Publishes events to Redis Pub/Sub channels via PHP's `ext-redis` extension. Subscribers (SSE proxy, WebSocket gateway) must be running separately — Redis Pub/Sub is fire-and-forget.

### WebSocket Driver

For applications running their `ez-php/websocket` server and broadcast producer in the
same PHP process. Delivery is synchronous and in-memory — no separate subscriber
gateway to run. Requires `ez-php/websocket` (`require-dev` only on this package; add it
to your own application's `composer.json`):

```php
use EzPhp\Broadcast\Broadcaster;
use EzPhp\Broadcast\Driver\WebSocketDriver;
use EzPhp\WebSocket\ChannelManager;

$channels = new ChannelManager(); // the same instance your WebSocket handler subscribes connections to
$broadcaster = new Broadcaster(new WebSocketDriver($channels));
```

Not config-driven like the other drivers — there is no config value that can express
"the `ChannelManager` instance my running `Server` was constructed with," so wire it
into the container yourself rather than setting `BROADCAST_DRIVER=websocket`.

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

Server-Sent Events are an HTTP wire format and live in `ez-php/http` since 2.0:
`EzPhp\Http\Sse\SseEvent` and `StreamedResponse::sse()`. Return the response from a
controller — it travels through middleware (auth, CORS) like any other response:

```php
use EzPhp\Http\Sse\SseEvent;
use EzPhp\Http\StreamedResponse;

public function events(Request $request): StreamedResponse
{
    // Check authorisation here, before returning — headers are sent before the first event.
    return StreamedResponse::sse(function (): \Generator {
        foreach ($this->subscription() as $message) {
            yield new SseEvent(json_encode($message, JSON_THROW_ON_ERROR), 'message');
        }
    });
}
```

The 1.x `SseStream` / `SseResponse` classes and the `$response->emit(); exit;` pattern
were removed: they bypassed middleware and `terminate()`. See
`ez-php/docs/upgrade-1.x-to-2.0.md`.

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

## Channel Authorization

Pass a `ChannelAuthorizerInterface` to `Broadcaster` to gate publishing per channel:

```php
use EzPhp\Broadcast\Broadcaster;
use EzPhp\Broadcast\ChannelAuthorizerInterface;

final class TeamChannelAuthorizer implements ChannelAuthorizerInterface
{
    public function authorize(string $channel): bool
    {
        return str_starts_with($channel, 'team.' . currentUser()->teamId() . '.');
    }
}

$broadcaster = new Broadcaster($driver, new TeamChannelAuthorizer());
$broadcaster->to('team.42.updates', 'TaskCompleted', []); // throws BroadcastException if denied
```

`event()` and `to()` both go through the same check. With no authorizer (the default), every
channel is allowed — existing code is unaffected. This is a single allow/deny decision, not a
presence-channel/member-list system.

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

`BroadcastException` (extends `RuntimeException`) is the base exception for this package. `Broadcast::event()` / `Broadcast::to()` throw `RuntimeException` if called before the broadcaster is set, and `BroadcastException` if a configured `ChannelAuthorizerInterface` denies the channel.
