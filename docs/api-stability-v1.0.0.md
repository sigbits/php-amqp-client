# v1.0.0 Public API Stability Audit

Date: 2026-09-30

## Decision

v1.0.0 public API stability is ready for the first stable compatibility
commitment.

Audit status: release readiness is verified in
`docs/release-readiness-v1.0.0.md`.

The public client surface has been narrowed and documented enough for the
v1.0.0 stabilization milestone. This audit separates the stable-candidate
surface from PHP-visible implementation details that remain outside the
compatibility promise.

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

Resolved.

Session, Sender, Receiver, and Delivery constructors are hidden from the supported public API.

Construction now flows through the public lifecycle methods:
`Connection::beginSession()`, `Session::openSender()`,
`Session::openReceiver()`, and `Receiver::receiveDelivery()`. Internal
composition and unit-test setup use `Sigbits\Amqp\Client\Internal\ClientObjectFactory`,
which is explicitly outside the supported user-facing API.

### Connection Test Seam

Resolved.

Connection::connect() no longer exposes SaslStreamConnector.

The supported public connection API now keeps normal user concerns on
`Connection::connect()`: URI parsing, optional SASL client selection, container
ID, timeout, and TLS options. Deterministic tests and lower-level composition
use `Sigbits\Amqp\Client\Internal\ClientObjectFactory::connection()`, which is
explicitly outside the supported user-facing API.

### Message Model Commitment

Resolved.

Message model commitment: `Sigbits\Amqp\Protocol\Message\Message`,
`Header`, `Properties`, and `MessageBodySection` are committed user-facing
types for v1.0.0 even though they live under the `Protocol` namespace.

### Timeout And Credit Semantics

Resolved.

Timeout unit commitment: connection setup uses `timeoutSeconds` as seconds,
including fractional seconds, and receiver wait operations use
`timeoutMilliseconds` as whole milliseconds.

Receiver credit commitment: receiver link credit is explicit and caller
controlled through `Session::openReceiver(..., credit: $credit)` for initial
credit and `Receiver::grantCredit($credit)` for replenishment.

### Exception Boundaries

Resolved.

Exception retry boundaries are committed for the current public client API.

`ClientException` and `TransportException` are the intended public exception
types for user-facing operations. Lower-level protocol and engine exceptions
must not leak through stable client workflows.

## Committed User-Facing Semantics

These commitments define the v1.0.0 behavior users can rely on. Future
backward-compatible releases may add message section support, more exception
detail, or richer retry helpers, but they must not silently change these
boundaries.

### Message Model Commitment

`Sender::send()` accepts either a `Message` object or a string. A string is
sent as a DATA-body `Message` with the string as its binary body. `Receiver::receive()`
returns `?Message`, and `Receiver::receiveDelivery()` returns a `Delivery`
whose `message()` method returns the same `Message` model.

The committed v1.0.0 message model supports these user-facing sections:

- `Header` fields for durable, priority, TTL, first-acquirer, and delivery
  count.
- Delivery annotations, message annotations, application properties, and
  footer maps with non-empty string keys. Map values are strings or signed AMQP
  scalar wrappers: `Byte`, `Short`, `Int_`, and `Long_`.
- `Properties` fields for message ID, correlation ID, content type, and
  subject.
- DATA bodies, AMQP value string bodies, and AMQP sequence bodies containing
  strings.

Encoding limits are part of the public model. AMQP value bodies and sequence
items use string8-sized values, map names use symbol8-sized values, string map
values use string8-sized values, and DATA bodies may use vbin8 or vbin32.
Unsupported AMQP section shapes and scalar types remain protocol-internal
details, not v1.0.0 user-facing API.

### Timeout Unit Commitment

`Connection::connect(..., timeoutSeconds: $timeoutSeconds)` interprets the
timeout as seconds and accepts fractional values. The same value is used for
opening the stream and for subsequent blocking reads performed by the public
connection/session/link workflows.

`Receiver::receive($timeoutMilliseconds)` and
`Receiver::receiveDelivery($timeoutMilliseconds)` interpret the timeout as
whole milliseconds. A timeout of `0` performs a non-blocking poll of already
available receiver state plus any immediate read attempt. Negative timeout
values are treated as an already-expired receive deadline and return `null`
when no delivery is already buffered.

A receiver wait that reaches its deadline returns `null`. A transport read
timeout while reading required stream bytes raises `TransportException::readTimedOut()`.
Callers may retry a receive that returned `null` on the same receiver. After a
transport timeout, the safest retry boundary is a fresh connection unless the
application has verified the stream and protocol state externally.

### Receiver Credit Commitment

`Session::openReceiver($address, ..., $credit)` grants the initial receiver
credit after the link opens. The default initial credit is `1`. The client does
not automatically replenish credit after deliveries are received.

`Receiver::grantCredit($credit)` sends a FLOW update using the number of
deliveries already returned by this receiver and the caller-supplied credit
window. A credit value of `0` advertises no additional available credit.
Positive values advertise additional capacity. Negative values are outside the
supported public contract for v1.0.0 and callers must not use them.

Credit is link-local and caller-managed. Long-running consumers should settle
deliveries according to their application policy, then explicitly call
`grantCredit()` when they are ready to receive more messages.

### Exception Retry Boundaries

Public connection, session, sender, receiver, and delivery operations expose
client lifecycle failures through `ClientException` and transport failures
through `TransportException`.

The v1.0.0 retry guidance is:

- `TransportException::connectionFailed()` means no AMQP connection was opened;
  retry by creating a new connection after applying the application's backoff
  policy.
- `TransportException::unexpectedEndOfStream()`,
  `TransportException::readTimedOut()`, and `TransportException::writeFailed()`
  mean the stream state is uncertain; close the current connection and retry on
  a fresh connection if the operation is idempotent at the application level.
- `ClientException::remoteConnectionClosed()` means the peer closed the AMQP
  connection; reopen the connection before retrying.
- `ClientException::remoteSessionEnded()` means the peer ended the session;
  begin a new session before retrying link operations.
- Sender or receiver detach exceptions mean the link is no longer usable; open
  a new link before retrying.
- `ClientException::senderLinkCreditExhausted()` means sending could not obtain
  credit before the underlying wait failed; retry only after reopening the link
  or otherwise confirming that credit is available.
- Settlement failures such as detached receiver settlement or duplicate
  delivery settlement must not be retried against the same `Delivery` object.

## Current Status

Ready for v1.0.0 tagging with the release-readiness audit as the final
verification record.
