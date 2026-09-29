# Changelog

All notable changes to this project are documented here.

This project does not create placeholder release tags. Every tagged version
must have release notes in this file before the tag is created.

## Unreleased

### Added

- Public receiver delivery settlement with `Receiver::receiveDelivery()` and delivery `accept()`, `release()`, and `reject()` methods.
- Public `Sender::detach()` and `Receiver::detach()` lifecycle methods with clear client exceptions for post-detach send/receive attempts.
- Deterministic public client exceptions when a remote peer detaches a sender or receiver link while it is opening.
- Broker integration coverage for public DATA message sending through Qpid Broker-J with an explicitly created test queue.
- Broker integration coverage for public DATA message receiving through Qpid Broker-J with an explicitly created test queue.
- Broker integration coverage for public accepted-delivery settlement through Qpid Broker-J with an explicitly created test queue.
- Public link-open exceptions now include remote AMQP DETACH error condition and description when the peer supplies them.

### Fixed

- Public sender attach now includes a source terminus as well as a target terminus, allowing Qpid Broker-J to accept the sender link.
- Public receiver attach now includes a source terminus and target terminus, allowing Qpid Broker-J to accept the receiver link and deliver queued messages.
- DETACH decoding now accepts compact AMQP uint encodings such as `uint0` handles sent by Qpid Broker-J.

## v0.5.0 - 2026-09-29

### Added

- Minimal public `Connection::connect()` API for opening authenticated AMQP 1.0 stream connections.
- Minimal public session API with `Connection::beginSession()` and `Session::end()` lifecycle methods.
- Minimal public sender API with `Session::openSender()` and `Sender::send()` for string or message payloads.
- Minimal public receiver API with `Session::openReceiver()` and blocking `Receiver::receive()`.
- Public stream reads now distinguish timeout failures from unexpected EOF with a dedicated transport error.
- Public `Connection::close()` and `Session::end()` lifecycle calls are idempotent.

## v0.3.0 - 2026-09-29

### Added

- TLS-capable AMQP stream connection support through PHP stream contexts and `amqps://` URIs.
- SASL ANONYMOUS and PLAIN negotiation over AMQP streams.
- AMQP message header encode/decode support for durable, priority, and TTL fields.
- AMQP delivery annotations encode/decode support for symbol keys and string values.
- AMQP message annotations encode/decode support for symbol keys and string values.
- AMQP footer encode/decode support for symbol keys and string values after DATA bodies.
- AMQP sequence body encode/decode support for string list payloads.
- AMQP value body encode/decode support for string payloads.
- AMQP connection URI parsing with `amqp://` and `amqps://` TLS stream context options.
- Synchronous PHP stream connector that opens `tcp://` or `tls://` transports from connection URIs.
- Stream-level SASL authenticator for mechanism negotiation, client init, and outcome validation.
- Composed SASL stream connector that opens a PHP stream and authenticates it.
- AMQP SASL init encode/decode foundation for ANONYMOUS and PLAIN credentials.
- Transport-independent SASL client negotiator for ANONYMOUS and PLAIN mechanisms.
- AMQP SASL mechanisms encode/decode foundation for offered server mechanisms.
- AMQP SASL outcome encode/decode foundation for OK and failure responses.
- AMQP message properties encode/decode support for message ID, correlation ID, content type, and subject.
- AMQP application properties encode/decode support for string keys and string values.
- Docker broker integration test matrix for Apache Qpid Broker-J and Apache ActiveMQ Artemis AMQP 1.0 handshakes.
- Apache ActiveMQ Artemis integration test that round-trips a DATA message through a broker address.

### Notes

- Message map support is intentionally constrained to symbol/string keys and string values.
- Broker interoperability coverage includes connection handshakes against Qpid Broker-J and ActiveMQ Artemis, plus a DATA message round trip through ActiveMQ Artemis.

## v0.2.0 - 2026-09-28

### Added

- Minimal synchronous blocking receiver API over the receiver link engine.
- Receiver link accept, release, and reject settlement frame emission.
- AMQP DISPOSITION encode/decode foundation for accepted, released, and rejected settlements.
- Receiver link incoming TRANSFER assembly for DATA body messages.
- Byte-driven receiver link engine for ATTACH and DETACH lifecycle frames.

## v0.1.0 - 2026-09-28

### Added

- Sender link TRANSFER frame emission with message payload fragmentation.
- Minimal AMQP TRANSFER performative encode/decode foundation.
- Minimal AMQP message DATA body encode/decode foundation.
- Sender link credit tracking from remote AMQP FLOW frames.
- Minimal AMQP FLOW performative encode/decode foundation for link credit.
- Byte-driven sender link engine for ATTACH and DETACH lifecycle frames.
- Minimal AMQP ATTACH and DETACH performative encode/decode foundation.
- Byte-driven AMQP session engine for BEGIN and END lifecycle frames.
- Minimal AMQP BEGIN and END performative encode/decode foundation.

## v0.0.1 - 2026-09-28

### Added

- Project roadmap and Docker-first development workflow.
- AMQP codec foundation with null, boolean, and unsigned integer encodings.
- AMQP protocol header encode/decode foundation.
- AMQP frame header codec and incremental frame parser foundation.
- Minimal AMQP OPEN performative encode/decode foundation.
- Minimal AMQP CLOSE performative encode/decode foundation.
- Minimal byte-driven connection engine for protocol header, OPEN, and CLOSE.
- Connection engine lifecycle and protocol error handling.
- In-memory connection engine loopback proof script.
