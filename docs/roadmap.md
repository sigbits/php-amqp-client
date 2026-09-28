# Project Roadmap

## Goal

Build a production-capable AMQP 1.0 client implemented in native PHP and
installable through Composer.

## Constraints

- Support PHP 8.3 and newer.
- Keep the core AMQP protocol engine independent from socket and stream I/O.
- Develop protocol behavior test-first using exact binary fixtures.
- Run local development through Docker, not the host PHP CLI.
- Validate every change with tests, PHP-CS-Fixer, and PHPStan level 6.

## Milestones

### v0.0.1: Connection Foundation

- AMQP primitive codec foundation.
- AMQP frame parser with fragmented input support.
- Protocol header negotiation.
- OPEN/CLOSE encode, decode, and engine state handling.

### v0.1.0: Sender Path

- Sessions and sender links.
- Link credit tracking.
- Basic message encoding.
- TRANSFER support, including payload fragmentation.

### v0.2.0: Receiver Path

- Receiver links.
- Incoming transfer assembly.
- Blocking receive API in the synchronous client layer.
- Settlement with accept, release, and reject.

### v0.3.0: Security and Message Fidelity

- TLS using PHP stream facilities.
- SASL ANONYMOUS and PLAIN.
- Practical AMQP message sections.

### v0.5.0: Developer API Stabilization

- Stable Connection, Session, Sender, and Receiver APIs.
- Clear timeout and lifecycle semantics.
- Documented error model.

### v1.0.0: Production-Oriented Release

- Broad interoperability across Azure Service Bus, RabbitMQ AMQP 1.0, Apache
  ActiveMQ Artemis, and Qpid-compatible peers.
- Long-running worker validation.
- Protocol-state tests, binary fixtures, and parser fuzz/property tests.
- Backward-compatible public API commitment.

## Immediate Development Slice

Start with the smallest codec behavior:

1. Decode AMQP null (`0x40`).
2. Encode AMQP null (`0x40`).
3. Grow primitive coverage one AMQP type at a time.
