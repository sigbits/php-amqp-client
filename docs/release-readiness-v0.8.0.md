# v0.8.0 Release Readiness Audit

Date: 2026-09-30

## Decision

v0.8.0 is ready to tag as the protocol completeness and state hardening
milestone for the current public client surface.

This release does not claim full AMQP 1.0 specification coverage. The
milestone scope is the core type, performative, message-section, parser, state,
and public exception behavior needed by the current broker-tested synchronous
client workflows.

## Checklist

### Core Type Coverage

Ready.

- Unsigned integer primitives include `ubyte`, `ushort`, `uint`, compact
  `uint0`, and `smalluint` coverage.
- Signed scalar primitives include `byte`, `short`, `int`, and `long`
  encode/decode coverage.
- Shared scalar decoding is used by performative codecs that consume broker
  compact integer encodings.

Carry-forward scope:

- Full AMQP 1.0 scalar coverage beyond the current public workflows, including
  decimal, floating point, timestamp, UUID, arrays, and broad generic compound
  value support, remains future protocol expansion work.

### Protocol-State Regressions

Ready.

- Connection, session, sender link, and receiver link engines have strict
  ordering regressions for invalid lifecycle calls and unsupported remote
  performatives.
- Remote CLOSE, END, and DETACH handling is covered by public client
  regressions.
- Settlement races are covered for duplicate settlement, detached receiver
  settlement, and unknown receiver delivery IDs.
- Exhausted sender link credit maps to a deterministic public exception.

Carry-forward scope:

- Broker restart coverage beyond transport interruption remains in v0.9.0.

### Binary Fixtures

Ready.

- Exact binary fixtures cover all currently supported performative codecs:
  OPEN, BEGIN, ATTACH, FLOW, TRANSFER, DISPOSITION, DETACH, END, and CLOSE.
- Exact binary fixtures cover supported message sections: header, delivery
  annotations, message annotations, properties, application properties, DATA,
  AMQP sequence, AMQP value, and footer.
- Signed scalar values are covered in message-section maps.

Carry-forward scope:

- Error payload decoding for CLOSE and END is still intentionally unsupported
  and should be expanded with full AMQP error type support before 1.0.

### Parser And Malformed Payload Coverage

Ready.

- Frame header regressions cover truncated headers, invalid frame sizes, and
  invalid data offsets.
- Frame parser finalization rejects truncated buffered frame headers and
  payloads.
- Fragmented transfer coverage includes split message-section descriptors and
  incomplete fragmented transfer finalization.
- Message and performative codecs reject malformed descriptors, truncated
  payloads, invalid body encodings, and unconsumed described container payloads
  in the supported surface.

Carry-forward scope:

- The current parser coverage is fixture-driven property-style coverage, not a
  randomized fuzz harness. A larger fuzzing harness remains appropriate before
  1.0.

### Public Exception Mapping

Ready.

- Public transport failures distinguish timeout, unexpected EOF, write failure,
  and unexpected frame type.
- Public client failures distinguish remote connection close, remote session
  end, remote link detach, link detach during open, settlement after detach,
  duplicate settlement, and exhausted sender credit.
- Link detach during open includes remote AMQP error condition and description
  when supplied by the peer.

Carry-forward scope:

- A formal retry-boundary user guide remains planned for the 1.0 documentation
  milestone.

## Release Gate

Before tagging v0.8.0, run the Docker CI pipeline from the merge commit on
`main`:

```sh
make ci
```

Do not create the tag unless CI passes and `CHANGELOG.md` contains the v0.8.0
release notes.
