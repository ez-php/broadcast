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
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
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

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum` and
`opcache` → `OPCache` are existing exceptions the guess gets wrong).

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks all ~40
  `CLAUDE.md` copies as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 | — |
| `ez-php/queue` | 3310 | 6381 | — |
| `ez-php/rate-limiter` | — | 6382 | — |
| `ez-php/search` | — | — | 7701 |
| **next free** | **3311** | **6383** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/broadcast

Real-time event broadcasting for ez-php applications — pluggable publish drivers (Null, Log, Array, Redis), a `Broadcast` static facade, and a `BroadcastServiceProvider` for framework integration.

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
    ├── NullDriver.php              — silent discard (default)
    ├── LogDriver.php               — writes to a log file or error_log() when path is empty
    ├── ArrayDriver.php             — stores events in-memory; designed for testing
    └── RedisDriver.php             — publishes to Redis Pub/Sub channels via ext-redis

tests/
├── TestCase.php                — base PHPUnit test case
├── ApplicationTestCase.php     — thin wrapper around EzPhp\Testing\ApplicationTestCase
├── BroadcasterTest.php         — covers Broadcaster: event(), to(), driver delegation
├── BroadcastTest.php           — covers Broadcast facade: set, reset, event, to, uninitialized throw
├── BroadcastServiceProviderTest.php — covers BroadcastServiceProvider: bindings, default driver, facade wiring
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

### Server-Sent Events

SSE framing lives in `ez-php/http` since 2.0 (`EzPhp\Http\Sse\SseEvent`, `StreamedResponse::sse()`). It is an HTTP wire format, and keeping it here would force every module that streams SSE (e.g. `ez-php/ai`) to depend on broadcast. The 1.x `SseStream`/`SseResponse` were removed because their `emit(); exit;` pattern bypassed middleware and `terminate()`.

---

## Design Decisions and Constraints

- **`ez-php/contracts` as the only runtime dep** — `BroadcastServiceProvider` uses `ConfigInterface` and `ServiceProvider` from contracts. No dependency on `ez-php/framework`, `ez-php/http`, or `ez-php/events`.
- **`Broadcast` facade with fail-fast** — Throws `RuntimeException` if called before `setBroadcaster()`. Silent discards are worse than loud failures in development. `NullDriver` (the default) handles intentional silence.
- **`ArrayDriver` for testing** — Avoids the need for a mock framework. Tests inject a real `ArrayDriver` and read `eventsOn()`. Mocking `BroadcastDriverInterface` would lose the ability to verify ordering and payload structure.
- **Redis Pub/Sub driver** — `RedisDriver` uses `ext-redis` and `Redis::publish()` to push events to Pub/Sub channels. Subscribers (SSE proxy, WebSocket gateway) must be running separately; Redis Pub/Sub is fire-and-forget with no persistence. WebSocket support still requires a long-running process (Ratchet, Swoole) which is out of scope.
- **Test namespace isolation** — Top-level broadcast tests use `namespace Tests`. Driver tests use `namespace Tests\Broadcast\Driver` to avoid collision with `Tests\Driver\LogDriverTest` in `ez-php/mail`. PHPUnit discovers tests by directory scan, so namespace/directory mismatches are allowed.
- **`BroadcastServiceProvider` 50% method coverage** — Both `register()` and `boot()` are exercised by `BroadcastServiceProviderTest`. The 50% figure is a PCOV attribution artefact: `boot()` calls `Broadcast::setBroadcaster()` which is in another class; the line executing inside `boot()` is attributed to the callee. This is expected and acceptable.

---

## Testing Approach

- **No external infrastructure** — All tests run in-process. No MySQL, Redis, or network required.
- **`BroadcastServiceProviderTest` uses `ApplicationTestCase`** — A full application is bootstrapped to verify the provider binds and wires correctly. The default `getBasePath()` creates a temp dir with an empty `config/` subdirectory; `ConfigInterface::get('broadcast.driver', 'null')` returns `'null'` (the default), so `NullDriver` is selected.
- **`Broadcast::resetBroadcaster()` in setUp/tearDown** — Required in every test touching the `Broadcast` facade to prevent state leaking across test methods.
- **`LogDriver` empty-path test** — Uses `ini_set('error_log', $tmpFile)` to redirect `error_log()` output to a temp file for assertion; restores the original value in `finally`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---------|-----------------|
| WebSocket support | `ez-php/websocket` (RFC 6455 server on PHP Fibers) or application layer |
| Persistent message queuing | `ez-php/queue` — Redis Pub/Sub (this module) is fire-and-forget |
| Domain event dispatching (in-process) | `ez-php/events` |
| Queue-backed async broadcast | Application layer: push a job that calls `Broadcast::event()` |
| Channel authentication / presence channels | Application-level middleware or a future `ChannelAuth` addition |
| HTTP streaming / chunked transfer encoding | Application layer or `ez-php/http` |
| Client-side EventSource / WebSocket polyfills | Frontend, out of scope |
