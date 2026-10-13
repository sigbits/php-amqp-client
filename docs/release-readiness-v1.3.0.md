# v1.3.0 Release Readiness Audit

Date: 2026-10-13

## Decision

v1.3.0 is ready to tag as the performative and terminus coverage foundation
milestone once the verification gate below passes from the release commit.

This release expands AMQP 1.0 field fidelity for connection, session, and link
performatives without changing the stable public client lifecycle API.
`OPEN`, `BEGIN`, and `ATTACH` can now carry their v1.3 field models in
memory-level protocol fixtures, while negotiation and enforcement behavior
remain deliberately scoped to later state-machine milestones.

## Scope

### OPEN and BEGIN Field Models

Ready for the v1.3.0 scope.

- `OPEN` optional fields encode and decode max-frame-size, channel-max,
  idle-time-out, outgoing/incoming locales, offered/desired capabilities, and
  connection properties.
- `BEGIN` optional fields encode and decode remote-channel, next-outgoing-id,
  incoming-window, outgoing-window, handle-max, offered/desired capabilities,
  and properties.
- Exact binary fixture coverage exists at the codec layer for the supported
  field shapes.

Carry-forward scope:

- Negotiating and enforcing max-frame-size, channel-max, idle-time-out,
  handle-max, capabilities, and session window behavior remains future
  connection/session state-machine work.
- Multiplexed sessions and general channel dispatch remain outside the v1.3.0
  release scope.

### ATTACH and Terminus Field Models

Ready for the v1.3.0 scope.

- Source and target terminus value objects cover address, durability,
  expiry-policy, timeout, dynamic-node flags, dynamic-node properties, and
  capabilities.
- Source fields cover distribution-mode, filters, default-outcome, and
  outcomes.
- `ATTACH` fields cover sender/receiver settlement modes, unsettled maps,
  incomplete-unsettled, max-message-size, offered/desired capabilities,
  properties, and initial-delivery-count.
- `ATTACH` initial-delivery-count is validated against sender/receiver role
  rules.

Carry-forward scope:

- Stateful unsettled delivery behavior remains tracked under settlement.
- Dynamic source/target negotiation and selector-specific filter APIs remain
  future broker-interoperability and protocol-state work.
- Link settlement-mode negotiation and resumed deliveries remain future
  settlement milestones.

### Delivery-State Foundation

Ready as supporting protocol-model work.

- Accepted, rejected, and released delivery-state value objects can be encoded
  and decoded as AMQP described values.
- Receiver settlement continues to expose accepted, released, and rejected
  public outcomes.

Carry-forward scope:

- Rejected error fields, modified, received, transactional-state, delivery
  ranges, sender settlement, and transaction coordinator support remain future
  settlement work.

### Compatibility

Ready.

- Public `Connection`, `Session`, `Sender`, `Receiver`, and `Delivery`
  lifecycle APIs remain unchanged.
- Existing public message construction and settlement methods remain
  backwards compatible.
- New value objects are additive protocol-model surfaces used by codecs and
  engines.

## Verification Gate

Before tagging v1.3.0 from `main`, run:

```sh
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --debug
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff --sequential
git diff --check
make broker-down
```

Do not create the tag unless the verification commands pass from the release
commit and `CHANGELOG.md` contains the v1.3.0 release notes.

Observed release-prep results on 2026-10-13:

- `vendor/bin/phpunit --configuration phpunit.xml.dist`: OK, 642 tests, 986
  assertions, 56 skipped.
- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --debug`: OK,
  no errors.
- `PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff --sequential`:
  OK, 0 fixable files.
- `git diff --check`: OK.
- `make broker-down`: OK, no stopped containers.
