# Changelog

All notable changes to this project are documented here.

This project does not create placeholder release tags. Every tagged version
must have release notes in this file before the tag is created.

## Unreleased

### Added

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
