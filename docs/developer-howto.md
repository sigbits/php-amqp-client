# Developer HOWTO

This guide is for contributors working on the AMQP client itself. User-facing
connection and messaging examples live in the [User Guide](user-guide.md).

## Development Rules

- Use Docker for project commands unless you are doing a narrow local check.
- Keep protocol behavior test-first with exact binary fixtures where possible.
- Keep the protocol engines independent from stream or socket I/O.
- Prefer the existing public API and internal factory patterns over new seams.
- Run the smallest relevant test first, then the broader gate before commit.

## Local Setup

Install dependencies in the project container:

```sh
make install
```

Run the fast local quality gate:

```sh
make ci
```

`make ci` validates Composer metadata, installs dependencies, runs PHP-CS-Fixer
in dry-run mode, runs PHPStan, and runs the fast PHPUnit suite. The fast suite
does not start broker containers.

## Test Types

### Static and Fast Unit Tests

Use these for normal code and documentation iterations:

```sh
make test
make cs
make stan
make ci
```

These commands should stay quick and deterministic. Tests that need external
brokers are intentionally skipped in this path unless their environment flags
are set.

### Broker Integration Tests

Use broker integration tests when changing connection, session, sender,
receiver, message encoding, settlement, broker addressing, SASL, or broker
compatibility behavior:

```sh
make test-integration BROKER_READY_TIMEOUT=180
```

This recreates the broker stack and runs tests with `RUN_BROKER_TESTS=1`.
Current coverage includes Apache Qpid Broker-J, ActiveMQ Artemis, RabbitMQ 4,
and the Azure Service Bus emulator for the supported public workflows.

### TLS/SASL Security Tests

Use the security suite when changing TLS options, SASL negotiation, connection
URI parsing, certificate behavior, authentication failure behavior, or broker
security wiring:

```sh
make test-security BROKER_READY_TIMEOUT=180
```

This runs `TlsSaslBrokerTest` with `RUN_BROKER_SECURITY_TESTS=1`. It currently
covers ActiveMQ Artemis and RabbitMQ 4 through HAProxy-backed TLS endpoints.
Qpid Broker-J TLS/SASL coverage is a documented future matrix gap.

### Long-Running Worker Tests

Use long-running tests when changing lifecycle, receive loops, settlement,
credit, reconnect behavior, transport loss handling, or memory-sensitive worker
paths:

```sh
make test-long BROKER_READY_TIMEOUT=180
```

This runs tests with `RUN_LONG_TESTS=1`. The suite covers repeated lifecycle
cycles, send-only, receive-only, request/reply-like, bounded-credit, reconnect,
transport interruption, and large-message paths.

Useful tuning knobs:

```sh
make test-long LONG_TEST_CYCLES=10 LONG_TEST_RECONNECT_CYCLES=5
```

### Broker Restart Tests

Use broker restart tests when changing transport loss, public wait loops,
sender/receiver behavior during failures, settlement, or recovery docs:

```sh
make test-broker-restart BROKER_READY_TIMEOUT=180
```

This target uses host-side Docker orchestration to restart ActiveMQ Artemis
while PHPUnit waits inside the project container. Those tests are skipped in
plain `make test-long` because PHPUnit cannot restart the broker container by
itself.

### Soak Profiles

Use soak profiles to run focused long-worker workloads with configurable cycle
counts:

```sh
make test-soak SOAK_PROFILE=all BROKER_READY_TIMEOUT=180
```

Supported profiles are `all`, `send-only`, `receive-only`, `request-reply`,
`bounded-credit`, `reconnect`, and `large-message`.

For a quick release-smoke soak:

```sh
make test-soak SOAK_PROFILE=all LONG_TEST_CYCLES=1 LONG_TEST_SEND_CYCLES=1 LONG_TEST_RECEIVE_CYCLES=1 LONG_TEST_REQUEST_REPLY_CYCLES=1 LONG_TEST_CREDIT_CYCLES=1 LONG_TEST_CREDIT_WINDOW=1 LONG_TEST_RECONNECT_CYCLES=1 LONG_TEST_LARGE_MESSAGE_CYCLES=1 LONG_TEST_LARGE_MESSAGE_BYTES=512 BROKER_READY_TIMEOUT=180
```

### Release Verification Matrix

Before tagging a release candidate, run and record:

```sh
make ci
make test-integration BROKER_READY_TIMEOUT=180
make test-security BROKER_READY_TIMEOUT=180
make test-broker-restart BROKER_READY_TIMEOUT=180
make test-long BROKER_READY_TIMEOUT=180
make test-soak SOAK_PROFILE=all LONG_TEST_CYCLES=1 LONG_TEST_SEND_CYCLES=1 LONG_TEST_RECEIVE_CYCLES=1 LONG_TEST_REQUEST_REPLY_CYCLES=1 LONG_TEST_CREDIT_CYCLES=1 LONG_TEST_CREDIT_WINDOW=1 LONG_TEST_RECONNECT_CYCLES=1 LONG_TEST_LARGE_MESSAGE_CYCLES=1 LONG_TEST_LARGE_MESSAGE_BYTES=512 BROKER_READY_TIMEOUT=180
make broker-down
```

## Why Tests Are Skipped

Expected skips in plain PHPUnit are not accepted gaps by themselves. They are
gates that need Docker broker services or host-side orchestration.

- `RUN_BROKER_TESTS=1` enables broker integration tests.
- `RUN_BROKER_SECURITY_TESTS=1` enables TLS/SASL security tests.
- `RUN_LONG_TESTS=1` enables long-running worker tests.
- `AMQP_TOXIPROXY_API` enables transport-interruption tests through Toxiproxy.
- `AMQP_BROKER_RESTART_READY_FILE` and `AMQP_BROKER_RESTART_CONTINUE_FILE`
  enable host-orchestrated broker restart tests.

If a skipped test represents a missing broker capability rather than an
intentional suite gate, document it in [Broker Compatibility Notes](broker-compatibility.md)
and the relevant release-readiness audit.

## Broker Stack Lifecycle

Start or reset the broker stack:

```sh
make broker-reset BROKER_READY_TIMEOUT=180
```

Stop and remove broker containers and volumes:

```sh
make broker-down
```

Use `broker-down` after interrupted broker runs so later tests do not inherit
stale queues, connections, or emulator state.

## Adding Protocol Features

1. Add exact binary fixture tests for codec behavior.
2. Add malformed payload tests for truncation, unsupported constructors, and
   invalid descriptors.
3. Keep scalar parsing in shared helpers when possible.
4. Add engine-level tests for state transitions before changing public client
   behavior.
5. Add broker tests only after the protocol behavior is deterministic.

Protocol code should not know about PHP streams, sockets, Docker, or broker
management APIs.

## Adding Public Client Features

1. Start with the public API behavior and failure mode.
2. Add client tests for lifecycle, timeout, and exception mapping.
3. Update `tests/Client/PublicApiStabilityTest.php` only when the new public
   method is intentionally part of the compatibility surface.
4. Update the [User Guide](user-guide.md) when the feature changes supported
   user workflows.
5. Add broker coverage for every supported broker that can exercise the
   workflow.

Public methods must have deterministic retry boundaries. Avoid exposing
internal engines or transport resources until the compatibility impact is clear.

## Adding Broker Coverage

Add or update broker coverage when a feature depends on broker behavior, address
syntax, management setup, TLS/SASL behavior, or cloud-provider compatibility.

- Use broker management APIs only inside integration tests or broker tools.
- Prefer randomized test addresses or queues.
- Create explicit queues for brokers that do not auto-create targets.
- Add compatibility notes for broker-specific behavior and accepted gaps.
- Keep real cloud-provider tests opt-in behind explicit credentials.

## Transport Direction

The default transport is PHP streams. Do not add a raw socket backend directly
to public client objects.

The preferred sequence is:

1. Correct receive timeout and buffering semantics.
2. Add ergonomic delivery consumption on top of the synchronous API.
3. Introduce an internal transport abstraction.
4. Add WebSocket or raw socket transports only when a real broker workflow
   proves the benefit.

Raw sockets are useful only if they provide concrete value that streams do not:
socket options, better select behavior, local binding, diagnostics, or event
loop integration. AMQP over WebSockets is likely more valuable for cloud broker
compatibility than a raw socket backend.

## Release Checklist

Before creating a tag:

1. Confirm `CHANGELOG.md` has release notes for the version.
2. Refresh the relevant roadmap and release-readiness audit.
3. Run the release verification matrix.
4. Run `make broker-down`.
5. Commit the release record.
6. Create an annotated tag.
7. Push the branch and tag.
