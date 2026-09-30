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

### v0.6.0: Broker Interoperability Hardening

- Public receiver delivery settlement API.
- Public sender and receiver detach lifecycle API.
- Deterministic public exceptions for remote link detach during open.
- Broker coverage for public send, receive, accepted settlement, and link
  detach against ActiveMQ Artemis and Qpid Broker-J.

### v0.7.0: Long-Running Worker Hardening

- Opt-in long-running worker test harness.
- Repeated public connection, session, sender, and receiver lifecycle cycles
  against ActiveMQ Artemis and Qpid Broker-J.
- Repeated public send, receive, and accepted-delivery settlement cycles
  against ActiveMQ Artemis and Qpid Broker-J.
- Bounded receiver credit-window cycles with public credit replenishment against
  ActiveMQ Artemis and Qpid Broker-J.
- Repeated reconnect send, receive, and accepted-delivery settlement cycles
  against ActiveMQ Artemis and Qpid Broker-J.
- Broker failure recovery tests covering broker restart or transport
  interruption during active public send, receive, and settlement loops against
  ActiveMQ Artemis and Qpid Broker-J.
- Fragmented large DATA message round trips against ActiveMQ Artemis and Qpid
  Broker-J.
- Clean broker-state reset before broker integration and long-running worker
  suites.
- Basic memory-growth guard for long-running worker tests.

### v0.8.0: Protocol Completeness and State Hardening

- Complete core AMQP 1.0 type coverage needed for broker and cloud-provider
  interoperability.
- Add protocol-state regression tests for invalid frame ordering, remote close,
  remote end, remote detach, settlement races, and exhausted link credit.
- Expand exact binary fixtures for all supported performatives and message
  sections.
- Add parser fuzz/property tests for malformed frame headers, truncated
  payloads, invalid descriptors, and fragmented transfers.
- Harden public exception mapping so protocol, transport, timeout, and lifecycle
  failures are deterministic and documented.

Release readiness was audited in
[v0.8.0 Release Readiness Audit](release-readiness-v0.8.0.md). The milestone is
ready for tagging with the supported-surface scope documented there.

### v0.9.0: Production Interoperability Hardening

- Run broker interoperability suites against Azure Service Bus, RabbitMQ AMQP
  1.0, Apache ActiveMQ Artemis, and Qpid-compatible peers.
- Add broker restart coverage in addition to transport interruption coverage
  for active public send, receive, and settlement loops. ActiveMQ Artemis
  send-loop, receive-loop, and settlement-loop restart coverage is in place.
- Add configurable long-running soak profiles for send-only, receive-only,
  request/reply-like, bounded-credit, reconnect, and large-message workloads.
  Local ActiveMQ Artemis and Qpid Broker-J coverage is in place through
  `make test-soak`.
- Validate TLS/SASL combinations across supported brokers, including failed
  authentication and certificate validation paths. Local ActiveMQ Artemis and
  RabbitMQ 4 coverage is in place through `make test-security`; Qpid Broker-J
  TLS/SASL security coverage remains a documented local-matrix gap.
- Document broker-specific setup, known limitations, and compatibility notes.

Release readiness was audited in
[v0.9.0 Release Readiness Audit](release-readiness-v0.9.0.md). The milestone is
tagged as v0.9.0 with Qpid Broker-J TLS/SASL security coverage carried forward
as a documented local-matrix gap.

### v1.0.0: Production-Oriented Release

- Backward-compatible public API commitment for `Connection`, `Session`,
  `Sender`, `Receiver`, `Delivery`, connection URI parsing, TLS options, and
  public exception types.
- Production hardening suite is required for release: CI, broker integration,
  long-running worker tests, broker restart/interruption tests, protocol-state
  tests, binary fixtures, and parser fuzz/property tests.
- Stable error model with documented retry boundaries for transport loss,
  broker restart, remote close/end/detach, timeout, and settlement failures.
- Complete user documentation for installation, connection configuration,
  sending, receiving, settlement, credit management, TLS, SASL, broker
  interoperability, and long-running worker operation.
- Release checklist requires fresh verification against the supported PHP
  version matrix and the supported broker matrix before tagging.

Public API stability was audited in
[v1.0.0 Public API Stability Audit](api-stability-v1.0.0.md). The stable API
commitment is ready for tagging with the release-readiness audit as the final
verification record.

Release readiness was audited in
[v1.0.0 Release Readiness Audit](release-readiness-v1.0.0.md). The milestone is
ready for tagging with the documented broker and external-provider gaps carried
forward.

## Completed Development Slice

v1.0.0 production-oriented release work completed these API stability and
readiness steps:

1. Resolve the public visibility of `Session`, `Sender`, `Receiver`, and
   `Delivery` implementation constructors before committing to 1.0
   compatibility. This is complete; construction now flows through public
   lifecycle methods and internal composition uses an internal factory.
2. Decide whether `Connection::connect()` keeps `SaslStreamConnector` as a
   supported extension point or moves it behind a documented factory/test seam.
   This is complete; `Connection::connect()` no longer exposes the connector
   seam and deterministic tests use the internal client object factory.
3. Documented and locked the committed user-facing message model, timeout
   units, receiver credit semantics, and exception retry boundaries.
4. Refreshed the v1.0.0 release-readiness audit against the committed public API
   and production-hardening requirements.
