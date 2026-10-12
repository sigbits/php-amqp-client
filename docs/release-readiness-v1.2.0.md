# v1.2.0 Release Readiness Audit

Date: 2026-10-12

## Decision

v1.2.0 is ready to tag as the generic AMQP container and message-surface
coverage milestone.

This release expands AMQP 1.0 value fidelity in the message model without
changing the stable public client lifecycle API. Existing string DATA bodies,
string AMQP value bodies, string sequence items, message map usage, and the
original `Properties` named arguments remain supported.

## Scope

### Generic AMQP Containers

Ready.

- Generic list support covers `list0`, `list8`, `list32`, and nested values.
- Generic map support covers `map8`, `map32`, mixed AMQP key/value shapes, and
  nested values.
- Generic array support covers `array8`, `array32`, primitive, compound, and
  described element constructors.
- Generic described values support numeric and symbolic descriptors with nested
  AMQP values.

### Message Surfaces

Ready for the v1.2.0 scope.

- AMQP value bodies support generic AMQP values through `Message::$bodyValue`.
- Delivery annotations, message annotations, application properties, and footer
  maps support generic AMQP values while keeping public keys constrained to
  non-empty PHP strings encoded as AMQP symbols.
- Message properties cover the AMQP property field set, including
  message-id/correlation-id choices, user-id, addressing, content metadata,
  timestamps, grouping, and reply-to grouping.
- AMQP sequence bodies support generic AMQP values through
  `Message::$bodySequence` and use `list0`, `list8`, or `list32` as needed.

### Compatibility

Ready.

- Existing public client APIs remain unchanged.
- Existing `Message` construction with string DATA bodies, string AMQP value
  bodies, string sequence bodies, message maps, and the original `Properties`
  constructor named arguments remains valid.
- `Message::$bodySequence` is widened from `list<string>` to `list<mixed>`.
  Code that relied on old validation exceptions for non-string or long string
  sequence items will now see those values encoded as valid AMQP sequence
  items.

### Carry-Forward Scope

- Multiple DATA sections and multiple AMQP sequence sections are not modeled
  yet.
- Public message maps still expose non-empty PHP string keys encoded as AMQP
  symbols; arbitrary AMQP map keys are available in the generic codec but not in
  these message-section convenience arrays.
- Higher-level performatives and terminus fields still need focused expansion
  and broader generic container adoption.
- AMQP decimal value objects preserve exact interchange bytes. They do not
  perform arithmetic, normalization, or conversion to PHP `float`, because PHP
  has no native decimal floating-point type.

## Verification Gate

Before tagging v1.2.0 from `main`, run:

```sh
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --debug
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff --sequential
git diff --check
```

Do not create the tag unless the verification commands pass from the release
commit and `CHANGELOG.md` contains the v1.2.0 release notes.

Observed release-prep results on 2026-10-12:

- `vendor/bin/phpunit --configuration phpunit.xml.dist`: OK, 601 tests, 934
  assertions, 56 skipped.
- `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --debug`: OK,
  no errors.
- `PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run --diff --sequential`:
  OK, 0 fixable files.
- `git diff --check`: OK.
