# v0.9.0 Release Readiness Audit

Date: 2026-09-30

## Decision

v0.9.0 is ready to tag as the production interoperability hardening milestone
for the current public client surface.

This release does not claim full AMQP 1.0 interoperability with every broker or
cloud provider. The milestone scope is the local broker matrix and hardening
coverage now documented for RabbitMQ 4, ActiveMQ Artemis, and Qpid Broker-J,
with the known limitations below carried forward.

## Checklist

### Broker Interoperability Matrix

Ready.

- ActiveMQ Artemis, Qpid Broker-J, and RabbitMQ 4 are part of the local Docker
  broker matrix.
- Broker integration coverage exercises AMQP/SASL handshake, public
  connection/session mapping, public send, public receive, accepted-delivery
  settlement, and sender/receiver detach workflows.
- RabbitMQ AMQP 1.0 coverage uses queue address v2 semantics through
  `/queues/:queue` addresses and creates test queues through the management
  API.
- Qpid Broker-J tests create explicit queues through the broker management API
  because the local test broker does not auto-create random targets.

Carry-forward scope:

- Azure Service Bus and other external broker targets are not part of the local
  release gate. They should be added only after a reproducible broker behavior
  gap is identified or external credentials are available for CI.
- RabbitMQ exchange/routing-key address forms are not yet covered by the local
  RabbitMQ AMQP 1.0 matrix.

### Broker Restart And Transport Interruption

Ready.

- ActiveMQ Artemis broker restart coverage exercises active public receive,
  send, and settlement loops through the host-orchestrated
  `make test-broker-restart` harness.
- Transport interruption coverage remains in the long-running worker suite
  through the Toxiproxy-backed public message-loop recovery test.
- Restart tests verify post-restart recovery with a fresh AMQP round trip after
  the interrupted operation observes the failure.

Carry-forward scope:

- Broker restart coverage is currently Artemis-focused. Qpid Broker-J and
  RabbitMQ restart scenarios can be added later if their behavior diverges from
  the covered transport-loss and Artemis restart boundaries.

### Long-Running Worker Hardening

Ready.

- `make test-long` exercises repeated public lifecycle, send/receive,
  reconnect, bounded-credit, large-message, and transport-interruption
  workloads against the configured local brokers.
- `make test-soak` adds configurable profiles for `send-only`, `receive-only`,
  `request-reply`, `bounded-credit`, `reconnect`, and `large-message`
  workloads.
- Long-running worker coverage includes basic memory-growth guards and broker
  stack reset before test execution.

Carry-forward scope:

- The soak profiles are deterministic local stress profiles, not an
  open-ended endurance test. Longer duration runs should be scheduled outside
  the normal release gate when needed.

### TLS And SASL Security Coverage

Ready with documented local-matrix gap.

- `make test-security` verifies trusted TLS certificate validation, rejected
  untrusted certificate validation, and rejected invalid SASL PLAIN credentials
  against ActiveMQ Artemis and RabbitMQ 4 through local HAProxy TLS endpoints.
- TLS test certificates are repo-local fixtures under `docker/broker/tls` and
  are intended only for the local broker integration matrix.
- Qpid Broker-J TLS/SASL security coverage remains a documented local-matrix
  gap and is not a blocker for v0.9.0 because Qpid remains covered for AMQP
  handshake and public workflow interoperability over the standard local AMQP
  endpoint.

Carry-forward scope:

- Native broker TLS configuration, mutual TLS, and Qpid Broker-J TLS/SASL
  security coverage remain future hardening work.

### Broker Documentation

Ready.

- `docs/broker-compatibility.md` documents the supported local Docker matrix,
  broker management assumptions, RabbitMQ queue address semantics, TLS/SASL
  security coverage, and known limitations.
- The changelog contains unreleased v0.9.0 notes for the broker hardening
  features added since v0.8.0.

Carry-forward scope:

- User-facing 1.0 documentation still needs broader installation,
  configuration, retry-boundary, and long-running worker operation guides.

## Verification Evidence

Before tagging v0.9.0, run the Docker verification matrix from the merge commit
on `main`:

```sh
make ci
make test-integration
make test-security
make test-broker-restart
make test-soak SOAK_PROFILE=all LONG_TEST_CYCLES=1 LONG_TEST_SEND_CYCLES=1 LONG_TEST_RECEIVE_CYCLES=1 LONG_TEST_REQUEST_REPLY_CYCLES=1 LONG_TEST_CREDIT_CYCLES=1 LONG_TEST_CREDIT_WINDOW=1 LONG_TEST_RECONNECT_CYCLES=1 LONG_TEST_LARGE_MESSAGE_CYCLES=1 LONG_TEST_LARGE_MESSAGE_BYTES=512
make broker-down
```

Do not create the tag unless the full matrix passes from the merge commit on
`main` and `CHANGELOG.md` contains the v0.9.0 release notes.
