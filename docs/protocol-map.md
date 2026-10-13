# AMQP 1.0 Protocol Coverage Map

Date: 2026-10-12

## Purpose

This document maps the current implementation against AMQP 1.0 protocol areas
so full protocol coverage can be planned and measured. It is intentionally
audit-oriented: every area should be marked as implemented, partial, missing, or
not applicable to this client library.

This map is based on the current repository state and the post-v1 roadmap. It
does not replace exact binary fixtures, broker interoperability tests, or a
future section-by-section conformance checklist against the OASIS AMQP 1.0
specification.

## Status Legend

- `implemented`: Covered for the current intended semantics with unit tests.
- `partial`: Some useful behavior exists, but the implementation is not full
  AMQP 1.0 coverage.
- `missing`: No meaningful implementation exists yet.
- `not applicable`: A protocol or broker concern that should not be implemented
  in this client unless a future public API explicitly needs it.

## Coverage Summary

| Area | Status | Current shape | Target milestone |
| --- | --- | --- | --- |
| Protocol header and frame parsing | partial | AMQP/SASL headers and basic frames work; extended and negotiated behavior is limited. | v1.6 |
| Generic AMQP type system | partial | Null, booleans, integer subset including `ulong`, floating point, decimals, char, timestamp, UUID, binary, string, symbol, generic lists, generic maps, generic arrays, and generic described values are covered. | v1.5 |
| SASL client role | partial | ANONYMOUS and PLAIN client handshake. | v1.6 |
| Connection/session/link performatives | partial | Core performatives exist, and `OPEN` now carries its optional field model; most other optional fields and negotiation behavior are still absent. | v1.6 |
| Message sections | partial | Generic annotation/property maps, full properties, DATA, AMQP value, and generic single AMQP sequence sections exist. | v1.4 |
| Delivery settlement | partial | Receiver accepted/released/rejected settlement exists. | v1.7 |
| Delivery states | partial | Accepted, released, and rejected have concrete delivery-state value objects and described-value codec support. | v1.7 |
| Transactions | missing | No coordinator or transactional delivery state. | v1.7 |
| Idle timeout and heartbeats | missing | No idle-time-out negotiation or empty-frame heartbeat behavior. | v1.6 |
| Transport abstraction | partial | Engine is mostly I/O-independent, but public client still reads/writes PHP streams directly. | v1.9 |
| Raw socket transport | missing | Not started; should remain optional. | v1.10 |
| WebSocket transport | missing | Not started. | v1.11 |
| Async/event-loop adapters | missing | Not started. | v1.12 |

## Type System

| AMQP area | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| Null and booleans | implemented | `src/Protocol/Codec/Encoder.php`, `src/Protocol/Codec/Decoder.php` | `tests/Protocol/Codec/EncoderTest.php`, `tests/Protocol/Codec/DecoderTest.php` | Integrate into the eventual generic value tree. | P2 | v1.5 |
| Signed integers | implemented | `Byte`, `Short`, `Int_`, `Long_`; encoder/decoder support byte/short/int/long. | Type and codec tests. | Add generic canonicalization/round-trip coverage across nested containers. | P2 | v1.5 |
| Unsigned integers | partial | `UByte`, `UShort`, `UInt`, `ULong`; compact decode support for `uint0`, `smalluint`, `ulong0`, and `smallulong`. | Type, codec, scalar reader, and performative tests. | Container integration and canonicalization across nested values. | P1 | v1.5 |
| Floating point | implemented | `Float32`, `Float64`; generic encoder/decoder support `float` and `double`. | Type and codec tests. | Integrate into the eventual generic value tree and nested containers. | P2 | v1.5 |
| Decimal types | implemented | `Decimal32`, `Decimal64`, `Decimal128`; generic encoder/decoder preserve the fixed-width AMQP decimal interchange bytes. | Type and codec tests. | Public decimal arithmetic/conversion helpers are deliberately deferred because PHP has no native decimal floating-point type. | P2 | v1.5 |
| Char | implemented | `Char`; generic encoder/decoder support Unicode scalar values in UTF-32BE wire form. | Type and codec tests. | Integrate into the eventual generic value tree and nested containers. | P2 | v1.5 |
| Timestamp | implemented | `Timestamp`; generic encoder/decoder support signed 64-bit millisecond values and message property timestamp fields. | Type, codec, and message tests. | Broader higher-level codec adoption where AMQP timestamp fields appear. | P2 | v1.5 |
| UUID | implemented | `Uuid`; generic encoder/decoder support strict RFC 4122 text form, 16-byte wire encoding, and message-id/correlation-id choices. | Type, codec, and message tests. | Broader higher-level codec adoption where AMQP UUID fields appear. | P2 | v1.5 |
| Binary | partial | `Binary` value object plus generic `vbin8`/`vbin32` encode/decode; DATA bodies, message `user-id`, message-id/correlation-id choices, transfer tags, and SASL initial response also have local support. | Type, codec, message, transfer, and SASL tests. | Broader use in higher-level codecs. | P1 | v1.5 |
| String | partial | Generic PHP string encode/decode for `str8`/`str32`; message properties, message maps, and performative codecs also have local support. | Codec, message, and performative tests. | UTF-8 validation policy and broader higher-level codec adoption. | P1 | v1.5 |
| Symbol | partial | `Symbol` value object plus generic `sym8`/`sym32` encode/decode; SASL, message map keys, content type/encoding, and detach errors also have local support. | Type, codec, SASL, and message tests. | Broader higher-level codec adoption. | P1 | v1.5 |
| Lists | implemented | `AmqpList`; generic encoder/decoder support `list0`, `list8`, `list32`, and nested lists. Local list parsing also exists in performative/message/SASL codecs. | Type, codec, performative, SASL, and message tests. | Adopt generic lists in higher-level codecs where that reduces duplicate parsing. | P2 | v1.5 |
| Maps | implemented | `AmqpMap`; generic encoder/decoder support `map8`, `map32`, nested maps, mixed values, and arbitrary AMQP key/value shapes. Message maps still have constrained local parsing. | Type, codec, and message tests. | Adopt generic maps in higher-level codecs where that reduces duplicate parsing. | P2 | v1.5 |
| Arrays | implemented | `AmqpArray`; generic encoder/decoder support `array8`, `array32`, primitive, compound, and described element constructors, and nested arrays. SASL mechanisms still have local array8 parsing. | Type, codec, SASL mechanisms, and message tests. | Adopt generic arrays in higher-level codecs where that reduces duplicate parsing. | P2 | v1.5 |
| Described values | implemented | `AmqpDescribedValue`; generic encoder/decoder support numeric and symbolic descriptors plus nested described values. Message sections, performatives, SASL performatives, source/target, outcomes, and detach errors still have hard-coded local parsing. | Type, codec, and existing protocol tests. | Adopt generic described values in higher-level codecs where that reduces duplicate parsing. | P2 | v1.5 |
| Malformed type input | partial | Current codecs reject selected truncation and unsupported constructor paths. | Codec, scalar reader, message, frame, and performative tests. | Property/fuzz coverage across the full type system. | P0 | v1.5 |

## Frame Layer and Protocol Headers

| AMQP area | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| AMQP protocol header | implemented | `ProtocolHeader`, `ProtocolHeaderCodec` | `tests/Protocol/Header/*` | Include in conformance map with exact spec section links. | P2 | v1.8 |
| SASL protocol header | implemented | `ProtocolHeader::sasl()` and SASL authenticator use SASL header. | Header and SASL transport tests. | Challenge/response SASL flows. | P1 | v1.6 |
| Frame header codec | implemented | `FrameHeader`, `FrameHeaderCodec` | `tests/Protocol/Frame/FrameHeaderCodecTest.php` | Add negotiated max-frame-size interactions. | P1 | v1.6 |
| Frame parser | partial | Fragmented frame buffering, multiple frames per chunk, max-frame-size guard. | `tests/Protocol/Frame/FrameParserTest.php` | Extended header semantics, idle empty frames, negotiated frame size, frame type dispatch boundary. | P0 | v1.6 |
| AMQP frame type 0 | partial | Core engines use AMQP frames with type `0`. | Engine and client tests. | General frame dispatch across multiplexed channels. | P0 | v1.6 |
| SASL frame type 1 | partial | SASL authenticator reads/writes frame type `1`. | SASL transport tests. | SASL challenge/response frames and robust bad-frame sequencing. | P1 | v1.6 |
| Empty frames and heartbeats | missing | None. | None. | Idle-time-out negotiation, sending empty frames, receiving heartbeats, and timeout failure mapping. | P0 | v1.6 |

## SASL and Security Layer

| AMQP area | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| SASL mechanisms | implemented | `SaslMechanisms`, `SaslMechanismsCodec` | `tests/Protocol/Sasl/SaslMechanismsCodecTest.php` | None for the current one-step client mechanisms. | P2 | v1.6 |
| SASL init | partial | `SaslInit`, `SaslInitCodec`; ANONYMOUS and PLAIN. | `tests/Protocol/Sasl/SaslInitCodecTest.php` | Hostname field support and mechanism-specific challenge flows. | P1 | v1.6 |
| SASL challenge/response | missing | None. | None. | `sasl-challenge` and `sasl-response` performatives. | P1 | v1.6 |
| SASL outcome | implemented | `SaslOutcome`, `SaslOutcomeCodec` | `tests/Protocol/Sasl/SaslOutcomeCodecTest.php` | Additional data behavior for richer mechanisms. | P2 | v1.6 |
| SASL client mechanisms | partial | `SaslClient::anonymous()`, `SaslClient::plain()` | `tests/Protocol/Sasl/SaslClientTest.php` | EXTERNAL, SCRAM/OAuth-style mechanisms if future broker coverage requires them. | P2 | v1.6 |
| TLS stream configuration | partial | `TlsOptions`, `ConnectionUri`, `StreamConnector` | Transport and TLS/SASL integration tests. | Native broker TLS, mutual TLS matrix, transport-boundary refactor. | P1 | v1.9 |
| SASL server role | not applicable | None. | None. | Do not implement unless this library grows a server/peer mode. | P3 | none |

## Performatives

| Performative | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| OPEN | partial | `Open`, `OpenCodec`, `ConnectionEngine`; all `OPEN` fields can be encoded/decoded, including locales, capabilities, and properties. | `OpenCodecTest`, `ConnectionEngineTest`, client/integration tests | Negotiating/enforcing max-frame-size, channel-max, idle-time-out, capabilities, and properties in connection state. | P0 | v1.3 |
| BEGIN | partial | `Begin`, `BeginCodec`, `SessionEngine`; all `BEGIN` fields can be encoded/decoded, including handle-max, capabilities, and properties. | `BeginCodecTest`, `SessionEngineTest` | remote-channel mapping beyond one public session path, negotiating/enforcing handle-max and capabilities, and complete incoming/outgoing window behavior. | P0 | v1.3 |
| ATTACH | implemented | `Attach`, `AttachCodec`, `Source`, `Target`, sender/receiver link engines; source/target address, durability, expiry policy, timeout, dynamic, dynamic-node-properties, and capabilities can be encoded and decoded as value objects; source distribution-mode, filter, default-outcome, outcomes, snd/rcv settle modes, unsettled map, incomplete-unsettled, max-message-size, offered/desired capabilities, properties, and initial-delivery-count with sender/receiver role validation are also covered. | `AttachCodecTest`, link engine tests | None for the ATTACH field model; stateful unsettled delivery behavior remains tracked under settlement. | P0 | v1.3 |
| FLOW | partial | `Flow`, `FlowCodec`, link engines | `FlowCodecTest`, sender/receiver engine tests | drain, echo, properties, session-only flow, complete window behavior, credit edge cases. | P0 | v1.6 |
| TRANSFER | partial | `Transfer`, `TransferCodec`, sender/receiver engines | `TransferCodecTest`, link engine tests, large message tests | settled, state, resume, aborted, batchable, omitted repeated fields across continuation frames, full delivery-state handling. | P0 | v1.6 |
| DISPOSITION | partial | `Disposition`, `DispositionCodec`, receiver settlement | `DispositionCodecTest`, receiver/client tests | sender role, delivery ranges, settled false, received/rejected/released/modified/transactional-state with fields, batchable. | P0 | v1.7 |
| DETACH | partial | `Detach`, `DetachCodec`; detach errors decoded for public exceptions | `DetachCodecTest`, link/client tests | closed semantics, full error value decoding, detached link recovery rules. | P1 | v1.6 |
| END | partial | `End`, `EndCodec`, `SessionEngine` | `EndCodecTest`, session/client tests | Full error payload decoding and session recovery semantics. | P1 | v1.6 |
| CLOSE | partial | `Close`, `CloseCodec`, `ConnectionEngine` | `CloseCodecTest`, connection/client tests | Full error payload decoding, close handshake edge cases, remote close while in intermediate states. | P1 | v1.6 |

## Terminus and Source/Target Model

| AMQP area | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| Source terminus fields | implemented | `Source` and `AttachCodec` can encode and decode address, durability, expiry-policy, timeout, dynamic, dynamic-node-properties, distribution-mode, filter, default-outcome, outcomes, and capabilities. | Attach and receiver engine tests. | None for the source field set; selector-specific filter APIs remain tracked under filters/selectors. | P0 | v1.3 |
| Target terminus fields | implemented | `Target` and `AttachCodec` can encode and decode address, durability, expiry-policy, timeout, dynamic, dynamic-node-properties, and capabilities. | Attach and sender engine tests. | None for the target field set; dynamic target negotiation remains tracked under dynamic nodes. | P0 | v1.3 |
| Filters/selectors | missing | None. | None. | Described filter set support and broker interoperability tests. | P1 | v1.6 |
| Dynamic nodes | missing | None. | None. | Dynamic source/target negotiation and returned terminus fields. | P1 | v1.6 |
| Broker management APIs | not applicable | Test helpers create broker queues externally. | Tool and integration tests. | Keep outside core AMQP unless a future management client is explicitly designed. | P3 | none |

## Message Format and Sections

| Message area | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| Header section | partial | `Header`, `MessageCodec` | `MessageCodecTest` | Confirm full field validation and generic value integration. | P1 | v1.4 |
| Delivery annotations | partial | Non-empty string public keys encoded as AMQP symbols; generic AMQP values, `map8`, and `map32` decode/encode through the generic map codec. | `MessageCodecTest` | Public representation for arbitrary non-string annotation keys if future API needs them. | P1 | v1.4 |
| Message annotations | partial | Non-empty string public keys encoded as AMQP symbols; generic AMQP values, `map8`, and `map32` decode/encode through the generic map codec. | `MessageCodecTest` | Public representation for arbitrary non-string annotation keys if future API needs them. | P1 | v1.4 |
| Properties | implemented | All AMQP property fields are encoded/decoded through generic list values: message-id and correlation-id choices, user-id, to, subject, reply-to, content-type, content-encoding, absolute-expiry-time, creation-time, group-id, group-sequence, and reply-to-group-id. | `MessageCodecTest` | None for the AMQP property field set; public PHP value wrappers remain the compatibility boundary for AMQP-only types. | P0 | v1.4 |
| Application properties | implemented | Non-empty string public keys encoded as AMQP symbols; generic AMQP values, `string32`, `map8`, and `map32` decode/encode through the generic map codec. | `MessageCodecTest` | Revisit key constructor choice if strict AMQP application-property string-key enforcement becomes necessary. | P1 | v1.4 |
| DATA body | partial | `vbin8` and `vbin32` DATA sections. | Message and integration tests. | Multiple DATA sections and generic binary value integration. | P0 | v1.4 |
| AMQP sequence body | partial | A single AMQP sequence section supports generic AMQP values through `list0`, `list8`, and `list32`. | `MessageCodecTest` | Multiple sequence sections. | P1 | v1.4 |
| AMQP value body | implemented | Any single generic AMQP value; `Message::$bodyValue` carries non-string values and explicit null, while string values remain available through `Message::$body` for backwards compatibility. | `MessageCodecTest`; generic codec tests. | Multiple AMQP value body sections are deliberately out of scope because the AMQP message body admits at most one `amqp-value` section. | P0 | v1.4 |
| Footer | partial | Non-empty string public keys encoded as AMQP symbols; generic AMQP values, `map8`, and `map32` decode/encode through the generic map codec. | `MessageCodecTest` | Public representation for arbitrary non-string footer keys if future API needs them. | P1 | v1.4 |
| Message format | partial | Transfer message-format is fixed to `0`. | Transfer and engine tests. | Validation and public behavior for non-zero message formats. | P2 | v1.6 |

## Delivery States, Settlement, and Transactions

| AMQP area | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| Accepted | implemented | `Accepted`, `DeliveryStateCodec`, `SettlementOutcome::Accepted`; receiver sends accepted disposition. | Delivery-state, disposition, receiver, client, integration tests. | None for the accepted field set. | P0 | v1.7 |
| Rejected | partial | `Rejected`, `DeliveryStateCodec`, `SettlementOutcome::Rejected`; receiver sends rejected with no error fields. | Delivery-state, disposition, and receiver tests. | Error field support and broker effect tests. | P0 | v1.7 |
| Released | partial | `Released`, `DeliveryStateCodec`, `SettlementOutcome::Released`; receiver sends released. | Delivery-state, disposition, and receiver tests. | Broker effect coverage across matrix. | P0 | v1.7 |
| Modified | missing | None. | None. | delivery-failed, undeliverable-here, message-annotations. | P0 | v1.7 |
| Received | missing | None. | None. | section-number, section-offset, resumable transfer behavior. | P1 | v1.7 |
| Transactional state | missing | None. | None. | txn-id plus nested outcome. | P0 | v1.7 |
| Sender settlement | missing | Sender sends transfers and consumes credit but does not track remote settlement. | Sender engine/client tests cover credit and detach, not settlement. | Unsettled outgoing deliveries, remote disposition handling, settlement modes, duplicate settlement. | P0 | v1.7 |
| Receiver settlement | partial | One-shot public `accept()`, `release()`, `reject()` per `Delivery`. | Receiver/client tests. | Ranges, settled negotiation, transactional settlement, stateful unsettled maps. | P0 | v1.7 |
| Transactions | missing | None. | None. | Coordinator link, declare, discharge, commit/rollback semantics, error mapping, broker tests. | P0 | v1.7 |

## State Machines and Client Workflows

| Area | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| Connection lifecycle | partial | `ConnectionEngine`, public `Connection` | Connection engine/client/integration tests | Full negotiation, idle timeout, close errors, intermediate-state edge cases. | P0 | v1.6 |
| Session lifecycle | partial | `SessionEngine`, public `Session` | Session engine/client/integration tests | Multiplexed sessions, channel allocation, full window behavior. | P0 | v1.6 |
| Sender link lifecycle | partial | `SenderLinkEngine`, public `Sender` | Sender engine/client/integration tests | Settlement modes, outgoing unsettled deliveries, remote disposition, resumed deliveries. | P0 | v1.7 |
| Receiver link lifecycle | partial | `ReceiverLinkEngine`, public `Receiver` and `Delivery` | Receiver engine/client/integration tests | Delivery ranges, transactional state, modified outcome, full credit/drain/echo behavior. | P0 | v1.7 |
| Fragmented transfers | partial | Sender fragments outbound messages; receiver assembles inbound payload. | Sender/receiver engine tests, long-running large DATA tests. | Continuation transfer semantics with omitted fields, aborted/resumed transfers, section offsets. | P0 | v1.6 |
| Error mapping | partial | Public client maps selected remote close/end/detach, timeout, transport loss, settlement races. | Client tests and release-readiness docs. | Full AMQP error condition decoding and retry classification. | P1 | v1.6 |
| Concurrency/multiplexing | missing | Public API can create sessions with explicit channel, but there is no general channel/link dispatcher. | Limited session/client tests. | Multiple active sessions and links sharing one connection with correct frame routing. | P0 | v1.6 |

## Transport and Interoperability

| Area | Status | Current implementation | Current tests | Missing behavior | Priority | Target |
| --- | --- | --- | --- | --- | --- | --- |
| Blocking PHP streams | implemented | `StreamConnector`, `SaslStreamConnector`, public `Connection` stream reads/writes. | Transport, client, integration, long-running tests. | Move frame I/O behind internal transport contract. | P1 | v1.9 |
| TLS over streams | partial | `TlsOptions`, `amqps://` URI handling. | Transport and TLS/SASL integration tests. | Native broker TLS matrix, mutual TLS, diagnostics behind transport boundary. | P1 | v1.9 |
| Internal transport boundary | partial | Engines are mostly byte-oriented; public client owns stream I/O. | Engine tests avoid sockets. | Dedicated transport interface for read/write/readiness/timeout/TLS/SASL handoff. | P0 | v1.9 |
| Raw socket backend | missing | None. | None. | Optional socket transport with TCP_NODELAY, keepalive, local bind, readiness, diagnostics, benchmarks. | P1 | v1.10 |
| WebSocket transport | missing | None. | None. | AMQP over WebSockets for cloud brokers, proxy/TLS/auth interaction tests. | P1 | v1.11 |
| Async/event-loop adapters | missing | None. | None. | ReactPHP/Amp/Revolt adapters over transport boundary. | P2 | v1.12 |
| ActiveMQ Artemis | partial | Local broker matrix covers public workflows and hardening. | Integration, security, long-running, restart tests. | Full-protocol behavior after v1.4-v1.8 expansion. | P1 | ongoing |
| Qpid Broker-J | partial | Local broker matrix covers standard public workflows. | Integration and long-running tests. | TLS/SASL local matrix and full-protocol behavior. | P1 | v1.3+ |
| RabbitMQ 4 | partial | Queue-address public workflows and TLS/SASL hardening. | Integration and security tests. | Exchange/routing-key address forms and full-protocol behavior. | P1 | v1.3+ |
| Azure Service Bus emulator | partial | Local emulator covers AMQP TCP public workflows. | Integration tests. | Real cloud coverage, WebSockets, Entra ID, cloud behavior. | P1 | v1.11 |

## Not Applicable Unless Scope Changes

| Area | Status | Rationale |
| --- | --- | --- |
| Broker node storage, routing, and queue semantics | not applicable | This is a client library; broker behavior belongs in broker-specific integration tests and documentation. |
| AMQP server listener role | not applicable | The public goal is a client. Server mode would be a separate architectural decision. |
| Broker management API | not applicable | Test helpers may use management APIs to create queues, but core AMQP protocol coverage should not depend on management behavior. |
| Provider-specific management features | not applicable | Azure/RabbitMQ/Artemis management should remain outside the core AMQP protocol engine unless a separate management client is designed. |

## Next Implementation Slice

The next implementation slices should adopt the generic value model in
higher-level codecs without mixing unrelated protocol behavior into one change.

Note on decimals: PHP does not provide a native decimal floating-point type.
The `Decimal32`, `Decimal64`, and `Decimal128` value objects therefore preserve
the exact AMQP decimal interchange bytes. They do not perform decimal
arithmetic, normalization, or conversion to PHP `float`, and callers must not
receive a silent float conversion. Those helpers should be designed explicitly
if a future public API needs them.

Recommended concrete slices:

1. Performatives and terminus fields: `OPEN` and `BEGIN` optional field
   encode/decode are in place for v1.3, and `ATTACH` now has source/target
   terminus value objects with address, lifecycle, dynamic-node-properties,
   capabilities, source distribution-mode, source filter, source
   default-outcome, source outcomes, `ATTACH` snd/rcv settle modes,
   `ATTACH` unsettled map, `ATTACH` incomplete-unsettled,
   `ATTACH` max-message-size, `ATTACH` offered/desired capabilities,
   `ATTACH` properties, and `ATTACH` initial-delivery-count fields with
   sender/receiver role validation. Prepare the v1.3.0 release notes and
   release-readiness audit next.
2. Delivery states and outcomes: accepted, rejected, and released are modeled
   as described values with exact binary fixtures. Continue with rejected error
   fields, modified, received, and transactional-state.
3. Message body multiplicity: model multiple DATA sections and multiple
   AMQP sequence sections with exact fixtures and broker regressions.

This order keeps full protocol coverage measurable and prevents transport work
from absorbing protocol bugs that should be caught in memory-level fixtures.
