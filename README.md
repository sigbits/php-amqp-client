# PHP AMQP Client

A pure-PHP AMQP 1.0 client library for PHP 8.3 and newer.

The goal is to provide a Composer-installable AMQP 1.0 client that does not
require Go, FFI, a PHP extension, or a sidecar process. The protocol engine is
designed to be independent from I/O so it can be tested without sockets and
later driven by blocking streams or async transports.

## Status

This project is in early development. The current release line includes the
protocol engine foundation, sender and receiver link engines, synchronous PHP
stream transport, TLS stream setup, SASL ANONYMOUS/PLAIN negotiation, and
practical AMQP message section encoding. The public API can open
connections, begin/end AMQP sessions, send messages, and receive messages.

The public developer API is still pre-1.0, but the `v0.6.0` release hardens
the synchronous Connection, Session, Sender, and Receiver surface with broker
interoperability coverage for sending, receiving, settlement, and link detach.

## Supported Surface

The current development line supports:

- PHP 8.3 and newer.
- `amqp://` TCP streams and `amqps://` TLS streams through PHP stream contexts.
- SASL ANONYMOUS and PLAIN.
- AMQP 1.0 protocol header, frame parsing, OPEN/CLOSE, BEGIN/END, ATTACH/DETACH,
  FLOW, TRANSFER, and DISPOSITION foundations.
- Message DATA, AMQP value, and AMQP sequence body sections.
- Header, properties, delivery annotations, message annotations, application
  properties, and footer sections for practical string-oriented messages.
- Public synchronous Connection, Session, Sender, and Receiver APIs.
- Documented public timeout, lifecycle, and transport error semantics.
- Public receiver delivery settlement through accepted, released, and rejected
  outcomes.

Current limitations:

- Message map support is intentionally constrained to symbol/string keys and
  string values.
- Broker interoperability coverage currently proves handshakes and public
  connection/session lifecycle against Qpid Broker-J and ActiveMQ Artemis,
  public sender/receiver detach lifecycle through both brokers, DATA message
  send/receive behavior through both brokers, and DATA settlement behavior
  through both brokers. Qpid Broker-J public link tests create an explicit
  queue because this test broker does not auto-create random targets.

## Public API Semantics

`Connection::connect($uri, timeoutSeconds: ...)` uses the timeout for opening
the stream and for blocking protocol reads on the public connection, session,
sender, and receiver paths.

Blocking protocol reads throw `TransportException` when the stream times out,
when the peer closes the stream unexpectedly, or when a complete payload cannot
be written. `Receiver::receive($timeoutMilliseconds)` returns `null` when no
message arrives before its receive deadline.

`Connection::close()` and `Session::end()` are idempotent once their lifecycle
has completed. The lower-level protocol engines remain strict state machines
and throw when used out of order.

`Sender::detach()` and `Receiver::detach()` close the AMQP link and wait for the
peer detach. Calling `Sender::send()` or `Receiver::receive()` after detach
throws `ClientException` with a link-specific message.

If a peer detaches a link while `Session::openSender()` or
`Session::openReceiver()` is still opening it, the public API throws
`ClientException` instead of continuing to read until a stream timeout. When the
peer includes an AMQP error condition and description on the DETACH, those
details are included in the exception message.

`Receiver::receive()` returns only the decoded message. Use
`Receiver::receiveDelivery()` when the application needs to explicitly settle a
delivery with `accept()`, `release()`, or `reject()`.

## Development

Development commands must run inside Docker:

```sh
make install
make test
make test-integration
make test-long
make cs
make stan
make ci
```

The package supports PHP 8.3 and newer. CI runs the quality pipeline across
supported PHP versions.

Broker integration tests run against Apache Qpid Broker-J and Apache ActiveMQ
Artemis containers:

```sh
make test-integration
make broker-down
```

Long-running worker hardening tests are opt-in and also use the broker
containers. They cover repeated public lifecycle cycles, repeated public send,
receive, and accepted-delivery settlement cycles, repeated reconnect message
cycles, and fragmented large DATA messages:

```sh
make test-long
make broker-down
```

Tune the default 100 lifecycle cycles with `LONG_TEST_CYCLES`, and tune the
default 8 MiB memory-growth threshold with `LONG_TEST_MAX_MEMORY_GROWTH_BYTES`.
Tune large-message coverage with `LONG_TEST_LARGE_MESSAGE_CYCLES` and
`LONG_TEST_LARGE_MESSAGE_BYTES`. Tune reconnect coverage with
`LONG_TEST_RECONNECT_CYCLES`.

The broker AMQP ports are also exposed on the host as `56720` for Qpid and
`56730` for Artemis.

## Roadmap

See [docs/roadmap.md](docs/roadmap.md) for the release roadmap and
[docs/amqp-1.0-php-iterative-plan.md](docs/amqp-1.0-php-iterative-plan.md) for
the detailed implementation plan.

## Releases

Release tags are created only when the corresponding roadmap milestone has
been reached. Every tagged version must have release notes in
[CHANGELOG.md](CHANGELOG.md) before the tag is created.
