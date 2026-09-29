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
practical AMQP message section encoding. The in-progress public API can open
connections, begin/end AMQP sessions, send messages, and receive messages.

The public developer API is not stable yet. Timeout semantics, lifecycle
semantics, and the documented error model are planned for the `v0.5.0`
milestone.

## Supported Surface

`v0.3.0` supports:

- PHP 8.3 and newer.
- `amqp://` TCP streams and `amqps://` TLS streams through PHP stream contexts.
- SASL ANONYMOUS and PLAIN.
- AMQP 1.0 protocol header, frame parsing, OPEN/CLOSE, BEGIN/END, ATTACH/DETACH,
  FLOW, TRANSFER, and DISPOSITION foundations.
- Message DATA, AMQP value, and AMQP sequence body sections.
- Header, properties, delivery annotations, message annotations, application
  properties, and footer sections for practical string-oriented messages.

Current limitations:

- Message map support is intentionally constrained to symbol/string keys and
  string values.
- The high-level developer API currently covers opening/closing connections,
  beginning/ending sessions, sending messages, and receiving messages.
- Broker interoperability coverage currently proves handshakes against Qpid
  Broker-J and ActiveMQ Artemis, and DATA message round-trip behavior through
  ActiveMQ Artemis.

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

## Development

Development commands must run inside Docker:

```sh
make install
make test
make test-integration
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
