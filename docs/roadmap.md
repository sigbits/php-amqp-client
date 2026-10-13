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

## Post-v1 Roadmap

The post-v1 direction is to move from a production-oriented synchronous client
subset toward full AMQP 1.0 protocol coverage before multiplying transport
backends. Transport work should follow a clear internal boundary so efficiency
and robustness improvements do not leak socket or stream concerns into the
protocol engine.

### v1.1: Developer Workflow and Receive Semantics

- Keep `make ci` as the fast local quality gate, but remove expected skip noise
  by separating unit/static checks from broker-dependent suites more clearly.
- Document the full test taxonomy and release verification matrix in the
  [Developer HOWTO](developer-howto.md).
- Fix receive timeout semantics so `Receiver::receive()` and
  `Receiver::receiveDelivery()` honor their method-level timeout without being
  dominated by the connection-level stream timeout.
- Add regression coverage for no-message receive deadlines and buffered
  deliveries.

### v1.2: Generic AMQP Containers in Messages

- Implement complete compound/container coverage for list0/list8/list32,
  map8/map32, array8/array32, described values, and nested values.
- Adopt the generic value model in AMQP value bodies, message maps, full
  message properties, and AMQP sequence bodies.
- Preserve AMQP decimal32/decimal64/decimal128 values as exact interchange
  bytes unless a future public API explicitly designs decimal arithmetic or
  conversion helpers. PHP has no native decimal floating-point type, so these
  values must not be silently converted to PHP `float`.
- Keep existing string-oriented message usage backwards compatible while
  widening message surfaces to richer AMQP value objects.

### v1.3: Performative and Terminus Coverage Foundation

- Adopt the generic AMQP value codec in performative codecs where it reduces
  duplicate list, map, array, symbol, and described-value parsing.
- Complete the `OPEN` field model with max-frame-size, channel-max,
  idle-time-out, outgoing/incoming locales, offered/desired capabilities, and
  connection properties.
- Expand `BEGIN` with handle-max, offered/desired capabilities, properties, and
  exact binary fixtures before changing session negotiation behavior.
- Start the `ATTACH` terminus model with source and target value objects so
  later slices can add durability, expiry policy, dynamic nodes, filters,
  outcomes, capabilities, and terminus properties without widening the public
  API accidentally.
- Keep protocol behavior memory-testable first; broker and transport work
  should validate the protocol model rather than define it.

Release readiness is audited in
[v1.3.0 Release Readiness Audit](release-readiness-v1.3.0.md). The milestone is
ready for tagging once the documented verification gate passes from the release
commit.

### v1.4: Functional Broker Coverage Expansion

- RabbitMQ exchange/routing-key address coverage is in place in addition to
  queue address coverage.
- Qpid Broker-J TLS/SASL coverage is wired into the local security matrix.
- Real Azure Service Bus cloud smoke coverage is available behind explicit
  opt-in credentials, keeping emulator coverage as the default local path.
- Azure Service Bus emulator broker message annotations decode compact signed
  integer scalars used for queue metadata such as sequence number and message
  state.
- RabbitMQ, ActiveMQ Artemis, and Qpid Broker-J settlement-effect coverage
  verifies accepted, released, and rejected delivery effects, including Qpid's
  broker-specific rejected-delivery redelivery behavior.
- Repeated DATA and AMQP sequence body sections have exact codec coverage, with
  Artemis broker regression coverage for both body forms.
- Synchronous generator-style `Receiver::deliveries()` API is in place without
  hiding explicit settlement or credit management.

### v1.5: Message Model Expansion

- Expand the committed message model toward full AMQP 1.0 message-section
  fidelity: header, delivery annotations, message annotations, properties,
  application properties, DATA, AMQP sequence, AMQP value, and footer.
- Support the full set of AMQP property fields, message-id/correlation-id
  choices, binary/string/symbol encodings, map key forms allowed by the
  specification, and broader scalar/container values.
- Preserve strict malformed-payload behavior, exact binary fixtures, and
  broker regression coverage for every newly supported section shape.

### v1.6: Full AMQP Type System

- Implement complete AMQP primitive coverage, including unsigned long, float,
  double, decimal32, decimal64, decimal128, char, timestamp, UUID, binary,
  string, and symbol encodings.
- Implement complete compound/container coverage for list0/list8/list32,
  map8/map32, array8/array32, described values, and nested values.
- Introduce value objects where PHP native types cannot preserve AMQP wire
  semantics, while keeping ergonomic conversion helpers at the public API
  boundary.
- Preserve AMQP decimal32/decimal64/decimal128 values as exact interchange
  bytes unless a future public API explicitly designs decimal arithmetic or
  conversion helpers. PHP has no native decimal floating-point type, so these
  values must not be silently converted to PHP `float`.
- Build property/fuzz tests and exact binary fixtures for every constructor,
  truncation path, unsupported constructor path, and round-trip guarantee.

### v1.7: Full Performative and State Coverage

- Model all fields for OPEN, BEGIN, ATTACH, FLOW, TRANSFER, DISPOSITION,
  DETACH, END, and CLOSE, including capabilities, properties, handle limits,
  settlement modes, filters, dynamic source/target nodes, unsettled maps, and
  error payloads.
- Complete connection, session, and link negotiation behavior, including
  max-frame-size, channel-max, idle-time-out, incoming/outgoing windows, handle
  allocation, and multiplexed sessions/links.
- Add idle-timeout and heartbeat behavior with deterministic timeout,
  half-open connection, and transport-loss semantics.
- Keep the protocol engine independent from I/O and test the state machines
  with memory/in-process transport fixtures before broker tests.

### v1.8: Settlement, Delivery States, and Transactions

- Implement the full delivery-state model: received, accepted, rejected,
  released, modified, transactional-state, and declared transaction IDs where
  applicable.
- Support sender-settle-mode and receiver-settle-mode negotiation, unsettled
  delivery tracking, delivery ranges, duplicate delivery handling, resumed
  deliveries, and settlement races.
- Add transaction coordinator support, including declare, discharge, successful
  commit, rollback, and failure mapping.
- Expand broker and conformance-style tests for settlement effects across
  supported brokers, while keeping unsupported broker-specific behavior
  documented instead of silently special-cased.

### v1.9: Protocol Conformance and Specification Traceability

- Maintain the [AMQP 1.0 Protocol Coverage Map](protocol-map.md) so implemented
  types, performatives, message sections, state transitions, delivery states,
  transaction behavior, SASL behavior, and transport requirements are linked to
  the OASIS specification sections.
- Treat full protocol coverage as a measurable release gate: every spec item is
  implemented, explicitly not applicable to a client library, or documented as
  a deliberate future extension.
- Expand malformed-input, fragmentation, fuzz/property, and interop suites so
  conformance failures are reproducible without a live broker wherever
  possible.
- Keep broker interoperability as a second gate after memory-level protocol
  conformance, not as a substitute for spec coverage.

### v1.10: Internal Transport Abstraction

- Introduce an internal transport boundary below SASL and the public client API.
- Keep PHP streams as the default transport.
- Move frame reading, write completion, timeout handling, idle-timeout
  scheduling, readiness checks, TLS/SASL handoff, and diagnostics behind the
  internal transport boundary.
- Define blocking transport contracts first, then leave room for non-blocking
  and event-loop adapters without changing protocol state machines.
- Do not expose raw transport resources as public API until the compatibility
  impact is intentionally designed.

### v1.11: Optional Socket Transport

- Add a raw socket backend only after the internal transport contract is stable.
- Keep PHP streams as the portable default; make sockets an optional backend for
  deployments that need lower-level control or measurable performance gains.
- Use sockets to unlock capabilities that streams cannot provide cleanly:
  TCP_NODELAY, keepalive tuning, local bind/source address control, explicit
  readiness checks, lower-level diagnostics, and cleaner non-blocking/event-loop
  integration.
- Require benchmark and robustness gates before promoting sockets: partial
  read/write behavior, TLS implications, reconnect behavior, broker restart,
  large-message fragmentation, idle-timeout handling, and memory-growth
  profiles.

### v1.12: AMQP over WebSockets and Cloud Transports

- Add AMQP over WebSockets when required for real Azure Service Bus cloud
  coverage or other production cloud broker scenarios.
- Validate WebSocket framing, TLS, proxy behavior, authentication interaction,
  reconnect semantics, idle-timeout behavior, and large-message fragmentation.
- Keep cloud-provider behavior behind documented configuration and integration
  tests rather than embedding provider-specific assumptions in the core AMQP
  engine.

### v1.13: Event-Loop and Async Adapters

- Add ReactPHP, Amp, Revolt, or other event-loop integration only as adapters
  over the internal transport boundary.
- Keep the synchronous API stable and additive; do not replace it unless a
  future major version intentionally introduces an async-first public API.
- Prove async adapters with the same protocol conformance fixtures, broker
  interoperability matrix, transport-loss tests, idle-timeout tests, and
  long-running worker profiles as the stream transport.
