# Changelog

All notable changes to this project are documented here.

This project does not create placeholder release tags. Every tagged version
must have release notes in this file before the tag is created.

## Unreleased

### Added

- AMQP message header encode/decode support for durable, priority, and TTL fields.
- AMQP SASL init encode/decode foundation for ANONYMOUS and PLAIN credentials.
- Transport-independent SASL client negotiator for ANONYMOUS and PLAIN mechanisms.
- AMQP SASL mechanisms encode/decode foundation for offered server mechanisms.
- AMQP SASL outcome encode/decode foundation for OK and failure responses.
- AMQP message properties encode/decode support for message ID, correlation ID, content type, and subject.
- AMQP application properties encode/decode support for string keys and string values.

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
