# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "0.*"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3310** | **6381** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/broadcast

Real-time event broadcasting for ez-php applications — pluggable publish drivers (Null, Log, Array), SSE frame formatting, a streaming response helper, a `Broadcast` static facade, and a `BroadcastServiceProvider` for framework integration.

---

## Source Structure

```
src/
├── BroadcastException.php          — base exception for all broadcast-related errors
├── BroadcastableInterface.php      — contract: broadcastOn(), broadcastAs(), broadcastWith()
├── BroadcastDriverInterface.php    — contract: publish(channel, event, payload): void
├── Broadcaster.php                 — orchestrates publishing via the injected driver
├── Broadcast.php                   — static facade backed by a managed Broadcaster singleton
├── BroadcastServiceProvider.php    — binds driver + Broadcaster; wires Broadcast facade in boot()
└── Driver/
│   ├── NullDriver.php              — silent discard (default)
│   ├── LogDriver.php               — writes to a log file or error_log() when path is empty
│   ├── ArrayDriver.php             — stores events in-memory; designed for testing
│   └── RedisDriver.php             — publishes to Redis Pub/Sub channels via ext-redis
└── Sse/
    ├── SseEvent.php                — single SSE frame: data, event name, id, retry + toString()
    └── SseStream.php               — iterable stream of SseEvents; getHeaders() + stream(Closure)

tests/
├── TestCase.php                — base PHPUnit test case
├── ApplicationTestCase.php     — thin wrapper around EzPhp\Testing\ApplicationTestCase
├── BroadcasterTest.php         — covers Broadcaster: event(), to(), driver delegation
├── BroadcastTest.php           — covers Broadcast facade: set, reset, event, to, uninitialized throw
├── BroadcastServiceProviderTest.php — covers BroadcastServiceProvider: bindings, default driver, facade wiring
├── SseEventTest.php            — covers SseEvent: getters, toString formatting, all fields, multi-line data
├── SseStreamTest.php           — covers SseStream: getHeaders(), stream() with arrays and generators
└── Driver/
    ├── NullDriverTest.php      — covers NullDriver: no exception, no output
    ├── LogDriverTest.php       — covers LogDriver: file write, append, directory creation, error_log fallback
    ├── ArrayDriverTest.php     — covers ArrayDriver: publish, eventsOn, isolation, reset
    └── RedisDriverTest.php     — covers RedisDriver: publish, multi-channel; requires live Redis (#[Group('redis')])
```

---

## Key Classes and Responsibilities

### BroadcastableInterface (`src/BroadcastableInterface.php`)

Contract for broadcastable event classes. Implement this on domain events that should be pushed to clients.

| Method | Description |
|--------|-------------|
| `broadcastOn(): string` | Channel name to broadcast on |
| `broadcastAs(): string` | Event name sent to the client |
| `broadcastWith(): array<string, mixed>` | Payload to include |

---

### BroadcastDriverInterface (`src/BroadcastDriverInterface.php`)

Single-method contract for all drivers:

```php
public function publish(string $channel, string $event, array $payload): void;
```

---

### Broadcaster (`src/Broadcaster.php`)

Orchestrates publishing. Accepts either a `BroadcastableInterface` (extracts channel, name, payload automatically) or explicit values via `to()`.

---

### Broadcast (`src/Broadcast.php`)

Static facade. Holds a `?Broadcaster` singleton set by `BroadcastServiceProvider::boot()`. Throws `RuntimeException` if called before `setBroadcaster()` — fail-fast prevents silent discards.

Global state is intentional and documented: the facade allows `Broadcast::event()` from anywhere without container access.

| Method | Description |
|--------|-------------|
| `event(BroadcastableInterface)` | Publish via interface methods |
| `to(string, string, array)` | Publish directly |
| `setBroadcaster(Broadcaster)` | Wire the singleton |
| `resetBroadcaster()` | Set to null (call in test tearDown) |

---

### BroadcastServiceProvider (`src/BroadcastServiceProvider.php`)

Reads `broadcast.driver` from `ConfigInterface`:

| Value | Driver instantiated |
|-------|---------------------|
| `'log'` | `LogDriver($config->get('broadcast.log_path', ''))` |
| `'array'` | `ArrayDriver()` |
| `'redis'` | `RedisDriver(host, port, database)` from `broadcast.redis.*` config |
| default | `NullDriver()` |

`register()` binds `BroadcastDriverInterface` and `Broadcaster` lazily. `boot()` eagerly resolves `Broadcaster` and calls `Broadcast::setBroadcaster()`.

---

### NullDriver (`src/Driver/NullDriver.php`)

All calls to `publish()` are no-ops. Default driver when `broadcast.driver` is unset or unknown.

---

### RedisDriver (`src/Driver/RedisDriver.php`)

Publishes events to Redis Pub/Sub channels via the PHP `ext-redis` extension. Throws `RuntimeException` at construction if the extension is not loaded.

- Each `publish()` call JSON-encodes `{'event': <name>, 'payload': <data>}` and calls `Redis::publish(channel, message)`
- Subscribers must be running separately (SSE proxy, WebSocket gateway, etc.) — Redis Pub/Sub is fire-and-forget
- Non-zero database selected via `Redis::select()` on construction
- Config keys: `broadcast.redis.host`, `broadcast.redis.port`, `broadcast.redis.database`

---

### LogDriver (`src/Driver/LogDriver.php`)

Writes a one-line summary per event to a file path. The log directory is created on demand. When `logPath` is empty, uses `error_log()`.

---

### ArrayDriver (`src/Driver/ArrayDriver.php`)

Stores events in `array<string, list<array{event, payload}>>`, grouped by channel. Provides `eventsOn(string $channel): list<...>` for assertions and `reset()` to clear state between tests.

---

### SseEvent (`src/Sse/SseEvent.php`)

Pure value object for a single SSE frame. `toString()` produces the wire format per RFC 8895:

```
[id: <id>\n]
[event: <event>\n]
[retry: <retry>\n]
data: <line1>\n
[data: <line2>\n]
\n
```

Multi-line data is handled automatically. `id`, `event`, and `retry` fields are omitted when empty/zero.

---

### SseStream (`src/Sse/SseStream.php`)

Wraps an `iterable<SseEvent>` (array or generator). Provides:
- `getHeaders(): array<string, string>` — the four SSE response headers
- `stream(\Closure(string): void $send)` — iterates events and passes each `toString()` to the sink

Decouples iteration from actual output — the controller decides how to flush/emit.

---

## Design Decisions and Constraints

- **`ez-php/contracts` as the only runtime dep** — `BroadcastServiceProvider` uses `ConfigInterface` and `ServiceProvider` from contracts. No dependency on `ez-php/framework`, `ez-php/http`, or `ez-php/events`.
- **`Broadcast` facade with fail-fast** — Throws `RuntimeException` if called before `setBroadcaster()`. Silent discards are worse than loud failures in development. `NullDriver` (the default) handles intentional silence.
- **`ArrayDriver` for testing** — Avoids the need for a mock framework. Tests inject a real `ArrayDriver` and read `eventsOn()`. Mocking `BroadcastDriverInterface` would lose the ability to verify ordering and payload structure.
- **`SseStream::stream()` accepts `\Closure`, not `callable`** — PHPStan level 9 can enforce the callable signature `\Closure(string): void` on a typed `\Closure` parameter. Plain `callable` loses the parameter type.
- **Redis Pub/Sub driver** — `RedisDriver` uses `ext-redis` and `Redis::publish()` to push events to Pub/Sub channels. Subscribers (SSE proxy, WebSocket gateway) must be running separately; Redis Pub/Sub is fire-and-forget with no persistence. WebSocket support still requires a long-running process (Ratchet, Swoole) which is out of scope.
- **No SSE retry/reconnection logic** — `SseEvent` supports the `retry` field in the frame format. Actual reconnection handling is client-side (the browser EventSource API handles it automatically).
- **Test namespace isolation** — Top-level broadcast tests use `namespace Tests`. Driver tests use `namespace Tests\Broadcast\Driver` to avoid collision with `Tests\Driver\LogDriverTest` in `ez-php/mail`. PHPUnit discovers tests by directory scan, so namespace/directory mismatches are allowed.
- **`BroadcastServiceProvider` 50% method coverage** — Both `register()` and `boot()` are exercised by `BroadcastServiceProviderTest`. The 50% figure is a PCOV attribution artefact: `boot()` calls `Broadcast::setBroadcaster()` which is in another class; the line executing inside `boot()` is attributed to the callee. This is expected and acceptable.

---

## Testing Approach

- **No external infrastructure** — All tests run in-process. No MySQL, Redis, or network required.
- **`BroadcastServiceProviderTest` uses `ApplicationTestCase`** — A full application is bootstrapped to verify the provider binds and wires correctly. The default `getBasePath()` creates a temp dir with an empty `config/` subdirectory; `ConfigInterface::get('broadcast.driver', 'null')` returns `'null'` (the default), so `NullDriver` is selected.
- **`Broadcast::resetBroadcaster()` in setUp/tearDown** — Required in every test touching the `Broadcast` facade to prevent state leaking across test methods.
- **`LogDriver` empty-path test** — Uses `ini_set('error_log', $tmpFile)` to redirect `error_log()` output to a temp file for assertion; restores the original value in `finally`.
- **`SseStream` generator test** — Passes a PHP generator as the iterable to verify `stream()` works with lazy sequences, not just arrays.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---------|-----------------|
| WebSocket support (Ratchet, Swoole) | A future `ez-php/websocket` module or application layer |
| Persistent message queuing | `ez-php/queue` — Redis Pub/Sub (this module) is fire-and-forget |
| Domain event dispatching (in-process) | `ez-php/events` |
| Queue-backed async broadcast | Application layer: push a job that calls `Broadcast::event()` |
| Channel authentication / presence channels | Application-level middleware or a future `ChannelAuth` addition |
| HTTP streaming / chunked transfer encoding | Application layer or `ez-php/http` |
| Client-side EventSource / WebSocket polyfills | Frontend, out of scope |
