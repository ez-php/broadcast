# Changelog

All notable changes to `ez-php/broadcast` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `BroadcastDriverInterface` — contract for all broadcast backends
- `NullDriver` — silently discards all broadcast events; useful in testing
- `LogDriver` — writes broadcast events to the application log for debugging
- `ArrayDriver` — stores broadcast events in memory; useful for in-process testing assertions
- `SseEvent` — value object representing a Server-Sent Event with event name, data, and optional ID
- `SseStream` — response helper that flushes `SseEvent` instances to the client over a long-lived HTTP connection
- `BroadcastableInterface` — marks a model or object as broadcastable with a channel name contract
- `Broadcaster` — internal dispatcher that routes events to the configured driver
- `Broadcast` — static facade for dispatching events from anywhere in the application
- `BroadcastServiceProvider` — binds the broadcaster and registers driver configuration
- `BroadcastException` for driver and serialization errors
