# PHP AMQP Client

A pure-PHP AMQP 1.0 client library for PHP 8.3 and newer.

The goal is to provide a Composer-installable AMQP 1.0 client that does not
require Go, FFI, a PHP extension, or a sidecar process. The protocol engine is
designed to be independent from I/O so it can be tested without sockets and
later driven by blocking streams or async transports.

## Status

This project is in early development. The first implementation focus is the
AMQP 1.0 binary codec, frame parser, and protocol engine foundation.

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
