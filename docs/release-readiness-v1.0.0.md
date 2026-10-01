# v1.0.0 Release Readiness Audit

Date: 2026-09-30

## Decision

v1.0.0 is ready to tag from the verified release candidate.

The public API stability audit has resolved the immediate API commitment
questions, and the user-facing guide now covers the committed public workflows.
Production-hardening verification passed for the release candidate that includes
`4cf4686` (`Fix AMQP hostname for proxied broker connections`). If additional
code changes are made after this audit, rerun the full release candidate
verification matrix before tagging.

## Checklist

### Committed Public API

Not blocked for audit entry.

- The stable-candidate API is documented in
  `docs/api-stability-v1.0.0.md`.
- `tests/Client/PublicApiStabilityTest.php` locks the current public method
  signatures for `Connection`, `Session`, `Sender`, `Receiver`, `Delivery`,
  `ConnectionUri`, `TlsOptions`, `ClientException`, and `TransportException`.
- `Session`, `Sender`, `Receiver`, and `Delivery` construction now flows through
  public lifecycle methods instead of supported public constructors.
- `Connection::connect()` no longer exposes the internal SASL stream connector
  composition seam.
- The committed message model, timeout units, receiver credit semantics, and
  exception retry boundaries are recorded for v1.0.0.

Carry-forward scope:

- Any additional public symbol exposed by PHP remains outside the v1.0.0
  compatibility promise unless it is added to the audited stable-candidate
  surface before tagging.

### Production Hardening Gate

Ready.

- The release gate requires `make ci`, broker integration tests, TLS/SASL
  security tests, broker restart tests, long-running worker tests, and soak
  profiles to pass from the release candidate commit.
- Parser, binary fixture, malformed payload, protocol-state, and public
  exception regressions passed through the `make ci` PHPUnit run.
- Broker restart coverage is currently ActiveMQ Artemis-focused. Transport-loss
  coverage remains in the long-running worker suite.

Verification performed on 2026-09-30:

```sh
make ci
make test-integration
make test-security
make test-broker-restart
make test-long
make test-soak SOAK_PROFILE=all LONG_TEST_CYCLES=1 LONG_TEST_SEND_CYCLES=1 LONG_TEST_RECEIVE_CYCLES=1 LONG_TEST_REQUEST_REPLY_CYCLES=1 LONG_TEST_CREDIT_CYCLES=1 LONG_TEST_CREDIT_WINDOW=1 LONG_TEST_RECONNECT_CYCLES=1 LONG_TEST_LARGE_MESSAGE_CYCLES=1 LONG_TEST_LARGE_MESSAGE_BYTES=512
make broker-down
```

Observed results:

- `vendor/bin/phpunit --configuration phpunit.xml.dist`: OK, 432 tests, 684
  assertions, 56 skipped.
- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist`: OK, no
  errors.
- `vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php`:
  OK, 0 fixable files.
- Focused Qpid transport-interruption regression through Toxiproxy: OK, 1 test,
  14 assertions.
- `make test-integration BROKER_READY_TIMEOUT=180`: OK, 35 tests, 45
  assertions, 6 skipped.
- `make test-security BROKER_READY_TIMEOUT=180`: OK, 6 tests, 6 assertions.
- `make test-broker-restart BROKER_READY_TIMEOUT=180`: OK for all three
  ActiveMQ Artemis restart scenarios, 3 tests, 14 assertions total.
- `make test-long BROKER_READY_TIMEOUT=180`: OK, 21 tests, 2460 assertions, 3
  skipped restart-only cases.
- `make test-soak SOAK_PROFILE=all LONG_TEST_CYCLES=1 LONG_TEST_SEND_CYCLES=1 LONG_TEST_RECEIVE_CYCLES=1 LONG_TEST_REQUEST_REPLY_CYCLES=1 LONG_TEST_CREDIT_CYCLES=1 LONG_TEST_CREDIT_WINDOW=1 LONG_TEST_RECONNECT_CYCLES=1 LONG_TEST_LARGE_MESSAGE_CYCLES=1 LONG_TEST_LARGE_MESSAGE_BYTES=512 BROKER_READY_TIMEOUT=180`:
  OK, 12 tests, 48 assertions.
- `make ci`: passed on PHP 8.3, including Composer validation, install, CS,
  PHPStan, and PHPUnit.
- `make broker-down`: broker containers stopped and removed after verification.

### Supported Broker Matrix

Ready with documented gaps.

- The local Docker matrix covers Apache ActiveMQ Artemis, Apache Qpid Broker-J,
  RabbitMQ 4, and Azure Service Bus emulator for core public client workflows.
- ActiveMQ Artemis and RabbitMQ 4 are covered by local TLS/SASL security tests
  through HAProxy-backed TLS endpoints.
- Qpid Broker-J remains covered for standard AMQP public workflows, but
  TLS/SASL security coverage is still a documented local-matrix gap.
- Azure Service Bus emulator coverage is local development-emulator coverage
  over AMQP TCP. The real Azure Service Bus cloud service remains an external-provider verification gap until reproducible credentials and CI
  workflow support exist.

Carry-forward scope:

- RabbitMQ exchange/routing-key address forms, native broker TLS configuration,
  mutual TLS, Qpid Broker-J TLS/SASL coverage, and external cloud-provider
  verification remain future compatibility work unless they are promoted into
  the release gate before v1.0.0.

### User Documentation Gate

Ready.

- `docs/broker-compatibility.md` documents the current local broker matrix and
  known broker-specific limitations.
- `docs/user-guide.md` covers installation, connection configuration, sending,
  receiving, delivery settlement, receiver credit management, TLS, SASL, retry
  boundaries, broker interoperability, and long-running worker operation.
- The README links to the user guide from the project status section.
- The v1.0.0 API stability audit remains the detailed record of the committed
  public semantics behind the user-facing guide.
- `CHANGELOG.md` must contain the final v1.0.0 release notes before the tag is
  created.

### Release Blockers

None for the verified release candidate.

## Current Status

Ready to tag v1.0.0 from the verified release candidate, with the documented
broker and external-provider gaps carried forward.
