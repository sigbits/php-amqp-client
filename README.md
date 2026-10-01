# PHP AMQP Client

A pure-PHP AMQP 1.0 client library for PHP 8.3 and newer.

The goal is to provide a Composer-installable AMQP 1.0 client that does not
require Go, FFI, a PHP extension, or a sidecar process. The protocol engine is
designed to be independent from I/O so it can be tested without sockets and
later driven by blocking streams or async transports.

## Status

This project is in early development. The `v1.0.0` release is the first stable
public API baseline for the documented synchronous workflows, not a
feature-complete AMQP 1.0 client.

It is suitable for evaluation and early production use when the documented
support matrix matches the application requirements. The supported surface
covers connection/session lifecycle, synchronous send/receive, receiver credit,
delivery settlement, TLS streams, SASL ANONYMOUS/PLAIN, and practical
string-oriented message sections.

Major planned areas still include broader AMQP type-system coverage, improved
delivery-consumption ergonomics, additional broker address forms, real Azure
Service Bus cloud verification, AMQP over WebSockets, and internal transport
abstraction work.

For user-facing connection, messaging, settlement, security, retry, broker, and
worker-operation guidance, see the [User Guide](docs/user-guide.md).

For contributor workflow, test taxonomy, broker gates, and release mechanics,
see the [Developer HOWTO](docs/developer-howto.md).

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
- Public receiver credit replenishment for bounded-credit worker loops.
- Deterministic public exceptions for remote close/end/detach, timeout,
  transport loss, settlement races, detached links, and exhausted sender link
  credit.

Current limitations:

- Message map support is intentionally constrained to symbol/string keys and
  string or signed scalar values.
- The v0.8.0 protocol completeness milestone is scoped to the current
  broker-tested public client workflows. Full AMQP 1.0 type-system coverage,
  broad compound values, and CLOSE/END error payload decoding remain future
  protocol expansion work.
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

Use `Receiver::grantCredit($credit)` to replenish receiver link credit after
consuming deliveries in bounded-credit worker loops.

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

See the [Developer HOWTO](docs/developer-howto.md) for when to use each test
type and why broker-dependent tests are skipped in the fast PHPUnit suite.

The package supports PHP 8.3 and newer. CI runs the quality pipeline across
supported PHP versions.

Broker integration tests run against Apache Qpid Broker-J, Apache ActiveMQ
Artemis, and RabbitMQ 4 containers. `make test-integration` resets broker
containers and volumes before running so stale broker state cannot affect the
suite:

```sh
make test-integration
make broker-down
```

Long-running worker hardening tests are opt-in and also use the broker
containers. They cover repeated public lifecycle cycles, repeated public send,
receive, and accepted-delivery settlement cycles, bounded receiver credit
windows, repeated reconnect message cycles, transport interruption recovery,
and fragmented large DATA messages. `make test-long` also resets broker
containers and volumes before running:

```sh
make test-long
make broker-down
```

Tune the default 100 lifecycle cycles with `LONG_TEST_CYCLES`, and tune the
default 8 MiB memory-growth threshold with `LONG_TEST_MAX_MEMORY_GROWTH_BYTES`.
Tune credit-window coverage with `LONG_TEST_CREDIT_CYCLES` and
`LONG_TEST_CREDIT_WINDOW`.
Tune large-message coverage with `LONG_TEST_LARGE_MESSAGE_CYCLES` and
`LONG_TEST_LARGE_MESSAGE_BYTES`. Tune reconnect coverage with
`LONG_TEST_RECONNECT_CYCLES`. Tune transport interruption recovery coverage
with `LONG_TEST_FAILURE_CYCLES`.

The broker AMQP ports are also exposed on the host as `56720` for Qpid,
`56730` for Artemis, and `56740` for RabbitMQ.

Use `make broker-reset` to manually recreate the broker test stack with clean
containers and volumes.

## Roadmap

See [docs/roadmap.md](docs/roadmap.md) for the release roadmap and
[docs/amqp-1.0-php-iterative-plan.md](docs/amqp-1.0-php-iterative-plan.md) for
the detailed implementation plan.

## Releases

Release tags are created only when the corresponding roadmap milestone has
been reached. Every tagged version must have release notes in
[CHANGELOG.md](CHANGELOG.md) before the tag is created.
