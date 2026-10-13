# v1.5.0 Release Plan

## Goal

v1.5.0 expands and hardens the AMQP message model around the remaining
message-section fidelity gaps after v1.4.0.

The release should avoid rebuilding capabilities that already landed in v1.4.0:
full AMQP property fields, repeated DATA and AMQP sequence bodies, AMQP value
bodies, broad generic map values, broker settlement coverage, and the
`Receiver::deliveries()` iterator are already in place.

## Theme

Message model expansion and strictness.

The main objective is to make message-section behavior more spec-faithful and
less ambiguous:

- application properties should use correct key semantics;
- malformed property sections should fail deterministically;
- annotations and footers should have a clear path for AMQP-valid key shapes
  beyond PHP string arrays;
- nested generic AMQP values should be proven through exact message-section
  fixtures;
- at least one richer message shape should be validated against brokers.

## Constraints

- Keep `Connection`, `Session`, `Sender`, `Receiver`, `Delivery`, and existing
  common `Message` construction backwards compatible.
- Develop codec behavior test-first with exact binary fixtures.
- Preserve strict malformed-payload behavior. Do not silently coerce invalid
  wire shapes into `null`.
- Prefer additive public surfaces over changing existing array ergonomics.
- Keep broker-specific behavior documented instead of hidden behind special
  cases.
- Validate each slice with targeted tests before running broader gates.

## Slice 1: Application Properties Key Correctness

### Problem

The shared message-map encoder currently writes map keys as AMQP symbols. That
is acceptable for annotations and footers, but AMQP application-properties map
keys are intended to be strings.

### Deliverables

- Encode application-property keys as AMQP strings.
- Add exact binary fixtures for `str8` keys and `str32` keys.
- Add decode coverage for incoming application-property maps with string keys.
- Decide and document decode compatibility for symbol keys:
  - preferred default: accept both string and symbol keys on decode for
    interoperability, while encoding strings going forward;
  - reject non-string, non-symbol keys as malformed.

### Main Files

- `src/Protocol/Message/MessageCodec.php`
- `tests/Protocol/Message/MessageCodecTest.php`
- `docs/protocol-map.md`
- `CHANGELOG.md`

### Acceptance Criteria

- Existing public `Message(applicationProperties: ['k' => 'v'])` construction
  still works.
- Encoded application properties use string keys in new exact fixtures.
- Symbol-key application-property decode behavior is intentional and covered.
- Invalid application-property keys fail with
  `Malformed AMQP application properties section.`

## Slice 2: Strict Properties Decode Validation

### Problem

`Properties` decode currently maps unsupported field types to `null` in several
places. For malformed AMQP property sections, that loses wire-level errors and
can make bad broker payloads look valid.

### Deliverables

- Reject wrong field types for each supported `Properties` field.
- Reject property lists with more than 13 fields.
- Keep omitted and explicit `null` trailing fields valid.
- Add exact malformed binary fixtures for the important field families:
  message-id/correlation-id choices, binary user-id, string address fields,
  symbol content fields, timestamp fields, and uint group-sequence.

### Main Files

- `src/Protocol/Message/MessageCodec.php`
- `src/Protocol/Message/MessageException.php`
- `tests/Protocol/Message/MessageCodecTest.php`
- `docs/protocol-map.md`
- `CHANGELOG.md`

### Acceptance Criteria

- Valid property fixtures from v1.4.0 keep passing.
- Wrong-type property fields throw `MessageException` with the existing
  malformed-properties message.
- The protocol map no longer describes property decode as silently nullable.

## Slice 3: Annotation and Footer Key Model

### Problem

Delivery annotations, message annotations, and footers are represented as
`array<string, mixed>`. That is ergonomic for common symbol-like keys, but AMQP
maps allow broader key shapes. The current model cannot represent every
spec-valid key without either stringifying or rejecting it.

### Deliverables

- Design an additive advanced map representation for message sections that need
  non-string keys.
- Keep existing arrays for common public usage.
- Add exact binary fixtures for at least one non-string key in:
  - delivery annotations;
  - message annotations;
  - footer.
- Document which key forms are supported by the simple array path and which
  require the advanced path.

### Main Files

- `src/Protocol/Message/Message.php`
- `src/Protocol/Message/MessageCodec.php`
- `tests/Protocol/Message/MessageCodecTest.php`
- `docs/user-guide.md`
- `docs/protocol-map.md`
- `CHANGELOG.md`

### Acceptance Criteria

- Existing array-based annotation/footer usage remains backwards compatible.
- Advanced key fixtures round-trip without losing key type information.
- Malformed key shapes still fail deterministically.

## Slice 4: Nested Generic Value Fixtures

### Problem

The generic codec supports nested lists, maps, arrays, and described values, but
v1.5.0 should prove those values through the public message surfaces, not only
through low-level codec tests.

### Deliverables

- Add exact encode/decode fixtures for nested generic values in:
  - delivery annotations;
  - message annotations;
  - application properties;
  - AMQP value body;
  - AMQP sequence body;
  - footer.
- Prefer one compact fixture per section over large fixture matrices.
- Update protocol-map status for message sections whose remaining value-shape
  gaps are closed.

### Main Files

- `tests/Protocol/Message/MessageCodecTest.php`
- `docs/protocol-map.md`
- `CHANGELOG.md`

### Acceptance Criteria

- Fixtures cover at least one nested container and one described value through
  message-section encode/decode.
- Existing generic codec behavior is reused; no duplicate nested parser is
  introduced in `MessageCodec`.

## Slice 5: Broker Regression for Rich Message Shapes

### Problem

Protocol fixtures prove wire behavior, but v1.5.0 should also show that at
least one richer message shape survives a real broker round trip where brokers
support it.

### Deliverables

- Add broker integration coverage for a richer message shape that is likely to
  survive broker round trips, preferably application properties plus message
  annotations.
- Run the test against the broker matrix where practical.
- Document broker-specific preservation or filtering behavior.

### Main Files

- `tests/Integration/PublicConnectionTest.php`
- `docs/broker-compatibility.md`
- `docs/protocol-map.md`
- `CHANGELOG.md`

### Acceptance Criteria

- At least one broker round trip validates richer message metadata.
- Unsupported broker behavior is documented rather than treated as a library
  failure.
- Broker tests remain opt-in behind the existing broker test environment.

## Slice 6: v1.5.0 Release Prep

### Deliverables

- Update `docs/roadmap.md` with the actual delivered v1.5.0 scope.
- Add `docs/release-readiness-v1.5.0.md`.
- Move `CHANGELOG.md` `Unreleased` content to `v1.5.0`.
- Run the release verification gate from `main` before tagging.

### Required Verification Gate

```sh
make ci
make test-integration
make test-security
git diff --check
make broker-down
```

If real Azure Service Bus credentials are available:

```sh
make test-azure-cloud
```

## Recommended Order

1. Application properties key correctness.
2. Strict properties decode validation.
3. Annotation and footer key model.
4. Nested generic value fixtures.
5. Broker regression for rich message shapes.
6. Release prep.

Start with application properties because it is the narrowest spec-facing gap
and will clarify whether the broader annotation/footer work needs a shared map
abstraction.
