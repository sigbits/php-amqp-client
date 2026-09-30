# v1.0.0 Public API Stability Audit

Date: 2026-09-30

## Decision

v1.0.0 is not ready to tag until the exposed implementation constructors are
resolved and the remaining user-facing API decisions are documented.

Audit status: v1.0.0 is not ready to tag until the exposed implementation constructors are resolved.

The current public client surface is close enough to begin the v1.0.0
stabilization milestone, but it is not yet safe to promise backward
compatibility for every public symbol that PHP exposes today. This audit
separates the stable-candidate surface from the pre-1.0 cleanup work that must
be completed before the final release.

## Stable-Candidate User Surface

These methods are the current user-facing API candidates for the 1.0
compatibility promise:

- `Connection::connect()`, `Connection::state()`,
  `Connection::beginSession()`, and `Connection::close()`.
- `Session::state()`, `Session::openSender()`, `Session::openReceiver()`, and
  `Session::end()`.
- `Sender::state()`, `Sender::availableCredit()`, `Sender::send()`, and
  `Sender::detach()`.
- `Receiver::state()`, `Receiver::receive()`,
  `Receiver::receiveDelivery()`, `Receiver::grantCredit()`, and
  `Receiver::detach()`.
- `Delivery::message()`, `Delivery::accept()`, `Delivery::release()`, and
  `Delivery::reject()`.
- `ConnectionUri::parse()`, `ConnectionUri::usesTls()`, and
  `ConnectionUri::streamContextOptions()`.
- `TlsOptions::streamContextOptions()` and the `TlsOptions` constructor.
- `ClientException` static factory methods for public client lifecycle and
  settlement failures.
- `TransportException` static factory methods for public URI, timeout, EOF,
  write, connection, and frame-type failures.

The current reflection contract for these methods is locked by
`tests/Client/PublicApiStabilityTest.php`. Any signature change must be treated
as an explicit API decision, not incidental cleanup.

## Required Pre-1.0 Decisions

### Exposed Implementation Constructors

Session, Sender, Receiver, and Delivery constructors expose engine and callable
internals. Those constructors are useful for tests and internal composition, but
they are not appropriate as a 1.0 user-facing construction API.

Risk: Session, Sender, Receiver, and Delivery constructors expose engine and callable internals.

Before tagging v1.0.0, choose one of these approaches:

- Hide construction behind public factories and make the constructors non-public
  where PHP visibility allows it.
- Keep the constructors public but explicitly document them as unsupported
  internals and accept that the visibility remains part of the PHP surface.
- Introduce interfaces for the user-facing surface and commit only to those
  interfaces while implementation classes remain internal.

The recommended path is to hide or replace the implementation constructors
before the 1.0 compatibility promise. Keeping them public would make engine and
callable details harder to evolve after 1.0.

### Connection Test Seam

`Connection::connect()` currently accepts an optional `SaslStreamConnector`.
That parameter is valuable for deterministic tests and advanced embedding, but
it exposes a lower-level transport composition detail.

Before v1.0.0, decide whether this remains a supported extension point or moves
behind a documented factory/test seam. If it stays, its lifecycle and exception
behavior need user documentation.

### Message Model Commitment

`Sender::send()` accepts `Message|string`, and `Receiver::receive()` returns
`?Message`. That makes `Sigbits\Amqp\Protocol\Message\Message` part of the
practical public surface even though it lives under the `Protocol` namespace.

Before v1.0.0, document which message sections are committed as supported user
API and which protocol details remain internal.

### Timeout And Credit Semantics

The public surface exposes timeout values in milliseconds for receive
operations and seconds for connection setup. It also exposes receiver credit
through `Receiver::grantCredit()`.

Before v1.0.0, document unit conventions, boundary behavior for zero and
negative values, and retry guidance for timeout outcomes.

### Exception Boundaries

`ClientException` and `TransportException` are the intended public exception
types for user-facing operations. Lower-level protocol and engine exceptions
should not leak through stable client workflows.

Before v1.0.0, run a focused error-model pass covering transport loss, broker
restart, remote close/end/detach, timeout, exhausted credit, and settlement
failures. Each boundary needs a deterministic public exception type and a
documented retry recommendation.

## Current Status

Ready to continue v1.0.0 stabilization.

Not ready to tag v1.0.0.

The next implementation slice should resolve the exposed implementation
constructors, then update this audit with the final compatibility commitment.
