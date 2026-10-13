# v1.4.0 Release Readiness Audit

Date: 2026-10-13

## Decision

v1.4.0 is ready to tag as the functional broker coverage expansion milestone
once the verification gate below passes from the release commit.

This release broadens practical broker interoperability coverage without
changing the stable public client lifecycle API. The release adds RabbitMQ
exchange/routing-key coverage, Qpid Broker-J TLS/SASL coverage, opt-in real
Azure Service Bus cloud smoke coverage, settlement-effect broker coverage,
repeated AMQP body-section broker regression coverage, compact Azure emulator
annotation decoding, and a synchronous delivery iterator.

## Scope

### Broker Addressing and Security Matrix

Ready for the v1.4.0 scope.

- RabbitMQ AMQP 1.0 exchange/routing-key send coverage verifies
  `/exchanges/:exchange/:routing-key` routing into a bound queue and receiving
  through `/queues/:queue`.
- Qpid Broker-J TLS/SASL security coverage is part of the local security
  matrix through the `qpid-tls` HAProxy endpoint.
- Real Azure Service Bus cloud smoke coverage is available behind explicit
  opt-in credentials and remains outside the default local matrix.

Carry-forward scope:

- Full Azure Service Bus cloud behavior, WebSockets, Entra ID authentication,
  private endpoint behavior, and cloud persistence/SLA behavior remain future
  cloud-transport and interoperability work.
- Native broker TLS and mutual TLS coverage remain future transport hardening
  work.

### Settlement and Broker Effects

Ready for the v1.4.0 scope.

- RabbitMQ, ActiveMQ Artemis, and Qpid Broker-J have broker-effect coverage for
  accepted, released, and rejected delivery outcomes.
- Qpid Broker-J rejected-delivery redelivery behavior is documented through the
  broker matrix instead of being normalized away.
- The AMQP `modified` delivery-state value object and codec support cover
  delivery-failed, undeliverable-here, and message-annotations fields.

Carry-forward scope:

- Public modified settlement APIs, received delivery-state ranges, sender
  settlement, transactional-state, and transaction coordinator behavior remain
  future settlement milestones.

### Message Body and Annotation Interoperability

Ready for the v1.4.0 scope.

- Repeated DATA body sections and repeated AMQP sequence body sections have
  exact codec coverage and Artemis broker regression coverage.
- Azure Service Bus emulator broker message annotations decode compact signed
  integer scalar forms used by queue metadata such as sequence number and
  message state.
- The synchronous `Receiver::deliveries()` iterator yields explicit
  `Delivery` objects without hiding settlement or credit management.

Carry-forward scope:

- Complete message-section fidelity, arbitrary annotation key representations,
  broader AMQP value-body surfaces, and full property-field behavior remain
  planned for later message-model expansion.

### Compatibility

Ready.

- Public `Connection`, `Session`, `Sender`, `Receiver`, and `Delivery`
  lifecycle APIs remain backwards compatible.
- New coverage and codec behavior are additive for existing public message,
  receive, and settlement workflows.
- Broker-specific behavior is documented in the compatibility matrix instead of
  changing public API semantics.

## Verification Gate

Before tagging v1.4.0 from `main`, run:

```sh
make ci
make test-integration
make test-security
git diff --check
make broker-down
```

If real Azure Service Bus credentials are available, also run:

```sh
make test-azure-cloud
```

Do not create the tag unless the required verification commands pass from the
release commit and `CHANGELOG.md` contains the v1.4.0 release notes.

Observed release-prep results on 2026-10-13:

- `make ci`: OK; Composer validation passed, install was already satisfied,
  PHP-CS-Fixer found 0 fixable files, PHPStan reported no errors, and PHPUnit
  passed with 686 tests, 1054 assertions, and 69 skipped.
- `make test-integration`: OK, 48 tests, 77 assertions, 10 skipped.
- `make test-security`: OK, 9 tests, 9 assertions.
- `git diff --check`: OK.
- `make broker-down`: OK, broker containers stopped and removed.

Optional cloud verification:

- `make test-azure-cloud` was not run during release prep because it requires
  explicit real Azure Service Bus credentials and a dedicated cloud test queue.
