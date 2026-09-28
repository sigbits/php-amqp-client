# Pure-PHP AMQP 1.0 Client --- Iterative Development Plan

## 1. Goal

Build a developer-friendly, production-capable **AMQP 1.0 client
implemented in native PHP**, installable through Composer and usable
without Go, FFI, a PHP extension, or a sidecar process.

The project should ultimately provide an experience similar in
simplicity to `php-amqplib`, while exposing AMQP 1.0 concepts accurately
enough for advanced use cases.

A representative target API:

``` php
use Amqp\Connection;
use Amqp\Message;

$connection = Connection::connect($_ENV['AMQP_URL']);

$sender = $connection->openSender('orders');

$sender->send(new Message(
    body: json_encode(['id' => 42]),
    contentType: 'application/json',
));

$receiver = $connection->openReceiver('orders');

if ($message = $receiver->receive(timeout: 30)) {
    process($message);
    $message->accept();
}
```

Initial interoperability targets:

-   Azure Service Bus
-   RabbitMQ AMQP 1.0
-   Apache ActiveMQ Artemis
-   Apache Qpid-compatible brokers/peers

The implementation should follow the OASIS AMQP 1.0 specification rather
than the behavior of any single broker.

------------------------------------------------------------------------

## 2. Guiding Principles

### Pure PHP first

The baseline package should require only PHP and Composer. Native
acceleration may be considered later, but must not be necessary for
correctness.

### Protocol engine independent from I/O

Do not let socket operations become intertwined with AMQP state
machines.

Conceptually:

``` text
incoming bytes
     |
     v
+----------------+
|  AMQP Engine   |
+----------------+
     |
     +---- events ----> application/client layer
     |
     +---- outgoing bytes ----> transport
```

This makes the protocol testable without a real network and leaves room
for blocking streams, Fibers, Revolt/Amp, ReactPHP, or other transports
later.

### Correctness before convenience

Implement the AMQP model faithfully internally. Convenience APIs should
be layered on top rather than replacing protocol concepts.

### Synchronous API first

Start with conventional blocking PHP. Do not make an async framework a
dependency of the protocol implementation.

### Interoperability continuously

Do not wait until v1.0 to test real brokers. Every major milestone
should end with interoperability tests.

### Small vertical slices

Each iteration should produce something executable and testable. Avoid
implementing the entire specification horizontally before attempting a
real connection.

------------------------------------------------------------------------

# Phase 0 --- Research and Architecture

**Estimated duration: 1 week**

## Iteration 0.1 --- Specification map

Read and map the AMQP 1.0 specification into implementation areas:

-   Types
-   Transport
-   Messaging
-   Security/SASL
-   Transactions

Create an internal implementation checklist linking protocol concepts to
specification sections.

### Deliverable

`docs/protocol-map.md`

The document should identify:

-   required functionality for MVP
-   required functionality for v1.0
-   optional/deferred functionality
-   protocol state machines
-   AMQP primitive and compound types
-   performatives
-   message sections
-   delivery states

### Exit criterion

The team can answer:

> Which exact portions of AMQP 1.0 must be implemented for a basic
> sender and receiver?

------------------------------------------------------------------------

## Iteration 0.2 --- Architecture spike

Define package boundaries before implementing protocol logic.

Suggested initial structure:

``` text
src/
  Client/
  Engine/
  Protocol/
    Codec/
    Type/
    Frame/
    Performative/
    Messaging/
  Transport/
  Exception/
```

Establish the key rule:

> `Engine` must not depend directly on PHP sockets or streams.

Define an initial transport abstraction and engine input/output model.

### Exit criterion

A memory/in-process transport can theoretically drive the engine without
changing protocol code.

------------------------------------------------------------------------

# Phase 1 --- AMQP Type System and Binary Codec

**Estimated duration: 2--3 weeks**

This is the foundation. Resist the temptation to connect to a broker
yet.

## Iteration 1.1 --- Primitive decoder

Implement decoding for the core AMQP primitive types.

Start with:

-   null
-   boolean
-   byte
-   short
-   int
-   long
-   unsigned byte
-   unsigned short
-   unsigned int
-   unsigned long
-   float
-   double
-   string
-   binary
-   symbol
-   timestamp
-   UUID

Introduce explicit PHP value objects where PHP's native types cannot
preserve AMQP wire semantics.

Example:

``` php
new Symbol('orders');
new Binary($bytes);
new UInt(42);
```

### Tests

For every type:

``` text
known AMQP bytes -> decoder -> expected PHP representation
```

Include:

-   boundary values
-   zero-width/small encodings
-   malformed input
-   truncated input

### Exit criterion

All supported primitive values decode deterministically from
specification-derived fixtures.

------------------------------------------------------------------------

## Iteration 1.2 --- Primitive encoder

Implement the inverse:

``` text
PHP/AMQP value -> encoder -> exact expected bytes
```

Test round trips:

``` text
value -> encode -> decode -> equivalent value
```

But do not rely only on round-trip tests: an encoder and decoder can
contain matching bugs. Keep independent expected-byte fixtures.

### Exit criterion

Primitive codec behavior is covered by exact binary fixtures.

------------------------------------------------------------------------

## Iteration 1.3 --- Compound and described types

Add:

-   list
-   map
-   array
-   described types

Pay particular attention to AMQP's compact encodings and width variants.

Design the decoder so unknown described types can be represented rather
than rejected automatically.

### Exit criterion

The codec can represent the data structures required to encode AMQP
performatives.

------------------------------------------------------------------------

# Phase 2 --- Frames and Performatives

**Estimated duration: 2 weeks**

## Iteration 2.1 --- Frame codec

Implement the AMQP frame header and payload handling.

Required capabilities:

-   frame size validation
-   data offset handling
-   frame type
-   channel
-   incremental parsing
-   incomplete-frame buffering
-   maximum-frame-size enforcement

The decoder must support receiving partial network reads.

For example:

``` text
read #1: first 3 bytes
read #2: next 17 bytes
read #3: remainder
```

must produce the same frame as receiving all bytes at once.

### Exit criterion

Frames can be split at every possible byte boundary and still decode
correctly.

------------------------------------------------------------------------

## Iteration 2.2 --- Transport performatives

Implement typed representations for:

-   Open
-   Begin
-   Attach
-   Flow
-   Transfer
-   Disposition
-   Detach
-   End
-   Close

Prefer generated or declaratively defined metadata over large amounts of
hand-written serialization logic.

Possible approach:

``` text
resources/spec/
  performatives.php
```

from which encoders/decoders or PHP classes can be generated.

### Exit criterion

Each performative can be encoded to known bytes and decoded back
independently.

------------------------------------------------------------------------

# Phase 3 --- Minimal Connection Engine

**Estimated duration: 2 weeks**

This is the first major vertical slice.

## Iteration 3.1 --- Protocol header

Implement AMQP protocol negotiation:

``` text
client -> AMQP protocol header
server -> AMQP protocol header
```

Reject incompatible protocol responses cleanly.

------------------------------------------------------------------------

## Iteration 3.2 --- Connection state machine

Implement:

``` text
START
  |
  +-- OPEN -->
  |
OPEN_SENT
  |
  <-- OPEN --
  |
OPENED
  |
  +-- CLOSE -->
  |
CLOSE_SENT
  |
  <-- CLOSE --
  |
CLOSED
```

The actual implementation should follow the specification's state
requirements rather than this simplified diagram.

Produce internal events such as:

``` text
ConnectionOpened
ConnectionClosed
ProtocolError
RemoteError
```

### First real interoperability milestone

Connect to one local AMQP 1.0 broker and successfully:

1.  negotiate the protocol
2.  exchange OPEN
3.  remain connected
4.  exchange CLOSE cleanly

### Exit criterion

A PHP script can open and cleanly close a real AMQP 1.0 connection.

**Tag candidate:** `v0.0.1`

------------------------------------------------------------------------

# Phase 4 --- Sessions

**Estimated duration: 1--2 weeks**

## Iteration 4.1 --- Session state

Implement:

-   local/remote channels
-   BEGIN
-   END
-   channel allocation
-   session lifecycle
-   session errors

Public API can initially remain simple:

``` php
$session = $connection->openSession();
$session->close();
```

------------------------------------------------------------------------

## Iteration 4.2 --- Session flow bookkeeping

Implement the session-level delivery/window counters needed by later
transfer handling.

Keep this internal initially.

### Exit criterion

Multiple sessions can be opened and closed independently on one
connection.

------------------------------------------------------------------------

# Phase 5 --- Sender Links and First Message

**Estimated duration: 2--3 weeks**

This is the most important early milestone.

## Iteration 5.1 --- Link model

Implement:

-   Source
-   Target
-   Attach
-   Detach
-   sender/receiver roles
-   handles
-   link names
-   settlement modes
-   link lifecycle

------------------------------------------------------------------------

## Iteration 5.2 --- Link credit

Implement incoming FLOW handling and sender credit tracking.

The sender must not transmit deliveries without appropriate credit.

Test:

``` text
credit = 0
send requested
-> no TRANSFER

FLOW credit = 10
-> pending send may proceed
```

------------------------------------------------------------------------

## Iteration 5.3 --- Basic AMQP message

Implement the minimum useful message representation:

``` php
$message = new Message(
    body: 'Hello world'
);
```

Initially support:

-   Data body
-   Properties
-   Application Properties

------------------------------------------------------------------------

## Iteration 5.4 --- Transfer

Implement delivery creation and TRANSFER.

Handle payload fragmentation when a message exceeds the negotiated
maximum frame size.

### Interoperability milestone

``` php
$connection = Connection::connect($dsn);
$sender = $connection->openSender('queue');
$sender->send(new Message('hello'));
```

Verify the message with an independent AMQP 1.0 client.

### Exit criterion

PHP can reliably send messages to at least two different AMQP 1.0
brokers/peers.

**Tag candidate:** `v0.1.0`

At this point, publish an experimental Composer package if desired.

------------------------------------------------------------------------

# Phase 6 --- Receiving and Settlement

**Estimated duration: 3 weeks**

## Iteration 6.1 --- Receiver links

Implement receiver-side link establishment and credit issuance.

Simple API:

``` php
$receiver = $connection->openReceiver('orders');
```

------------------------------------------------------------------------

## Iteration 6.2 --- Incoming transfer assembly

Support:

-   single-frame deliveries
-   multi-frame deliveries
-   partial network reads
-   multiple deliveries
-   aborted transfers where applicable

Convert complete deliveries into:

``` php
ReceivedMessage
```

------------------------------------------------------------------------

## Iteration 6.3 --- Blocking receive API

Implement:

``` php
$message = $receiver->receive(timeout: 30);
```

Do not put the blocking behavior inside the core AMQP state machine. The
synchronous client layer should drive the transport and engine until:

-   a message arrives
-   timeout occurs
-   connection/link fails

------------------------------------------------------------------------

## Iteration 6.4 --- Settlement

Implement at minimum:

``` php
$message->accept();
$message->release();
$message->reject();
```

Then add disposition tracking and settled/unsettled delivery state.

### Interoperability milestone

PHP receiver consumes messages produced by:

-   another PHP process
-   Go AMQP client
-   broker tooling/reference client

Test both directions.

### Exit criterion

A basic queue worker can run continuously and correctly settle
deliveries.

**Tag candidate:** `v0.2.0`

------------------------------------------------------------------------

# Phase 7 --- Messaging Model

**Estimated duration: 2--3 weeks**

Implement the complete practical message model.

## Message sections

Support:

-   Header
-   Delivery Annotations
-   Message Annotations
-   Properties
-   Application Properties
-   Data
-   AMQP Sequence
-   AMQP Value
-   Footer

Expose a friendly default API without hiding advanced sections.

Example:

``` php
$message = new Message(
    body: $payload,
    properties: new Properties(
        messageId: '123',
        correlationId: '456',
        contentType: 'application/json',
        subject: 'order.created',
    ),
    applicationProperties: [
        'tenant' => 'acme',
    ],
);
```

### Exit criterion

Messages round-trip through multiple independent AMQP implementations
without loss of supported metadata.

------------------------------------------------------------------------

# Phase 8 --- SASL, TLS and Authentication

**Estimated duration: 2 weeks**

## TLS

Use PHP's established stream/TLS facilities rather than implementing
TLS.

Support:

``` text
amqp://
amqps://
```

Provide explicit TLS configuration rather than relying only on DSN query
strings.

------------------------------------------------------------------------

## SASL

Implement the SASL protocol layer independently from AMQP transport
framing.

Initial mechanisms:

-   ANONYMOUS
-   PLAIN

Design for additional mechanisms without coupling them to connection
logic.

### Exit criterion

Authenticate successfully against all initial interoperability targets
that support the selected mechanisms.

**Tag candidate:** `v0.3.0`

------------------------------------------------------------------------

# Phase 9 --- Error Model and Reliability

**Estimated duration: 3--4 weeks**

## Iteration 9.1 --- PHP exception hierarchy

Provide PHP-native errors while preserving AMQP information.

Example:

``` text
AmqpException
|- ConnectionException
|- AuthenticationException
|- SessionException
|- LinkException
|- DeliveryException
|- ProtocolException
|- TimeoutException
`- ConnectionClosedException
```

Preserve:

-   AMQP condition
-   description
-   info map where available
-   local/remote origin

------------------------------------------------------------------------

## Iteration 9.2 --- Invalid peer behavior

Test:

-   malformed frames
-   illegal channel
-   unexpected performative
-   oversized frames
-   invalid state transitions
-   truncated messages
-   invalid descriptors

Never allow malformed remote input to produce unbounded allocations or
uncontrolled loops.

------------------------------------------------------------------------

## Iteration 9.3 --- Network failure behavior

Define predictable behavior for:

-   socket EOF
-   timeout
-   broker restart
-   connection reset
-   half-written frame
-   failure during settlement
-   remote detach/end/close

Initially prefer explicit failure over magical reconnection.

### Exit criterion

Failure behavior is documented and deterministic.

------------------------------------------------------------------------

# Phase 10 --- Developer-Friendly Public API

**Estimated duration: 2 weeks**

Until now, protocol correctness takes priority. Now stabilize the
developer-facing layer.

Support a convenience API:

``` php
$connection = Connection::connect($dsn);

$sender = $connection->openSender('orders');
$receiver = $connection->openReceiver('orders');
```

while retaining advanced access:

``` php
$session = $connection->openSession();

$sender = $session->openSender(
    target: new Target(...),
    options: new SenderOptions(...),
);
```

Develop:

-   immutable options objects
-   clear timeout semantics
-   sensible defaults
-   idempotent `close()`
-   predictable destructors
-   explicit lifecycle documentation

Avoid surprising work in destructors. Destructors should primarily
protect resources, not perform important protocol operations that
application correctness depends upon.

### Exit criterion

A PHP developer can perform common send/receive operations without
needing to understand AMQP framing or state machines.

**Tag candidate:** `v0.5.0`

------------------------------------------------------------------------

# Phase 11 --- Interoperability Matrix

**Estimated duration: ongoing; focused 2--4 week hardening period**

Build automated integration suites.

Suggested matrix:

  Feature            Azure Service Bus   RabbitMQ   Artemis   Qpid/reference
  ------------------ ------------------- ---------- --------- ----------------
  Connect/TLS                                                 
  SASL PLAIN                                                  
  Send                                                        
  Receive                                                     
  Properties                                                  
  App properties                                              
  Large messages                                              
  Accept                                                      
  Release                                                     
  Reject                                                      
  Link detach                                                 
  Connection close                                            

Do not add broker-specific behavior to the protocol core unless the AMQP
specification supports it.

If a broker requires extensions, isolate them in optional
compatibility/provider layers.

------------------------------------------------------------------------

# Phase 12 --- Long-Running Worker Hardening

**Estimated duration: 2--3 weeks**

AMQP clients often run for days or weeks. Test accordingly.

Build stress tests for:

``` text
send 1,000,000 small messages
receive 1,000,000 messages
open/close 100,000 links
repeated reconnect cycles
large message fragmentation
slow consumers
credit exhaustion
broker restart
network interruption
```

Track:

-   PHP memory usage
-   file descriptors
-   object counts
-   throughput
-   latency
-   unexpected allocations

Run selected tests for hours in CI/nightly environments.

### Exit criterion

No unbounded resource growth under representative long-running
workloads.

------------------------------------------------------------------------

# Phase 13 --- Framework Integrations

**After protocol/client stability**

Keep these outside the protocol core where possible.

Possible packages:

``` text
vendor/amqp
vendor/amqp-symfony-messenger
vendor/amqp-laravel
```

Do not make Symfony or Laravel dependencies of the AMQP package.

A Symfony Messenger transport would be a particularly useful real-world
validation target because it exercises long-running receiving,
settlement, retries and shutdown.

------------------------------------------------------------------------

# Phase 14 --- Async/Nonblocking Support

**Post-MVP; architecture should permit it from day one**

Do not rewrite the protocol engine.

Instead introduce different engine drivers/transports.

Possible architecture:

``` text
                 AMQP Engine
                     |
          +----------+----------+
          |                     |
          v                     v
Synchronous Driver       Async Driver
          |                     |
   PHP streams           Revolt/Amp/etc.
```

The engine should continue consuming bytes and producing:

-   outgoing bytes
-   state transitions
-   events

without knowing how I/O is scheduled.

Potential later APIs may use Fibers/promises, but avoid committing the
core package to one async ecosystem prematurely.

------------------------------------------------------------------------

# Phase 15 --- Optional Performance Optimization

Only do this after profiling.

Benchmark separately:

-   primitive encoding
-   primitive decoding
-   frame parsing
-   message encoding
-   network throughput
-   memory allocations

If the codec is a demonstrated bottleneck, consider an **optional**
native codec.

Preferred boundary:

``` text
PHP AMQP client/state machines
          |
          v
       Codec
      /     \
 PHP codec   optional native codec
```

Avoid moving connection/session/link state into native code unless there
is overwhelming evidence it is necessary.

Pure PHP must remain the reference implementation.

------------------------------------------------------------------------

# Phase 16 --- v1.0 Criteria

Do not define v1.0 merely as "enough features."

Release v1.0 when the API and behavior can reasonably be kept
backward-compatible.

Minimum proposed criteria:

-   stable Connection/Session/Sender/Receiver API
-   send and receive
-   major AMQP message sections
-   core AMQP type system
-   settlement
-   link credit/flow control
-   frame fragmentation/reassembly
-   TLS
-   SASL ANONYMOUS and PLAIN
-   meaningful AMQP error propagation
-   timeouts
-   clean shutdown
-   long-running worker validation
-   interoperability with multiple independent AMQP 1.0 implementations
-   Azure Service Bus interoperability
-   documented resource/lifecycle semantics
-   comprehensive binary codec fixtures
-   protocol-state tests
-   fuzz/property testing around parsers
-   no required native extension

**Target:** `v1.0.0`

------------------------------------------------------------------------

# Suggested Release Sequence

``` text
v0.0.1
  Connection OPEN/CLOSE

v0.1.0
  Sender + basic messages

v0.2.0
  Receiver + settlement

v0.3.0
  TLS/SASL + richer messaging

v0.4.0
  Reliability + AMQP errors

v0.5.0
  Developer-facing API stabilization

v0.6.x
  Interoperability fixes

v0.7.x
  Long-running worker hardening

v0.8.x
  Broader AMQP semantics

v0.9.x
  API freeze / release candidates

v1.0.0
  Stable supported API
```

Avoid promising dates for later releases. Let interoperability and field
testing determine progression.

------------------------------------------------------------------------

# Testing Strategy

Testing should be treated as a first-class implementation area.

## Layer 1 --- Codec fixtures

Thousands of small deterministic tests:

``` text
bytes <-> AMQP value
```

Use independently derived expected byte sequences.

## Layer 2 --- Frame fixtures

Test complete and fragmented input.

## Layer 3 --- State-machine tests

No sockets.

Example:

``` text
given:
  local OPEN sent

when:
  remote OPEN received

expect:
  connection state = OPENED
  ConnectionOpened event emitted
```

Cover invalid transitions too.

## Layer 4 --- In-memory peer simulations

Connect two engine instances through memory buffers.

This enables fast deterministic protocol tests without Docker or network
timing.

## Layer 5 --- Reference-client tests

Test PHP against mature AMQP 1.0 clients, including `go-amqp`.

## Layer 6 --- Real-broker tests

Run broker containers/services in CI where practical.

## Layer 7 --- Cloud interoperability

Maintain a small Azure Service Bus integration suite, preferably
separately from ordinary pull-request CI because it requires credentials
and external infrastructure.

## Layer 8 --- Fuzz/property tests

Prioritize externally supplied binary input:

-   type decoder
-   frame parser
-   message decoder
-   performative decoder

Important properties include:

``` text
decoder never hangs
decoder never reads beyond available input
invalid length cannot trigger huge allocation
encode/decode preserves supported values
frame splitting does not change decoded result
```

------------------------------------------------------------------------

# First 30 Days

A practical first-month plan:

## Week 1

-   repository and Composer setup
-   coding standards/static analysis
-   specification map
-   architecture document
-   binary buffer abstraction
-   primitive type model

**Goal:** architecture settled enough to code without prematurely
freezing the public API.

## Week 2

-   primitive encoder
-   primitive decoder
-   compound values
-   described values
-   extensive codec fixtures

**Goal:** reliable AMQP value codec.

## Week 3

-   frame parser
-   incremental input handling
-   performative model
-   OPEN/CLOSE encoding and decoding
-   initial protocol engine

**Goal:** feed raw OPEN/CLOSE frames through the engine without
networking.

## Week 4

-   synchronous stream transport
-   protocol header negotiation
-   connection state machine
-   connect to first real broker
-   clean OPEN/CLOSE
-   CI integration test

**Goal:**

``` php
$connection = Connection::connect($dsn);
$connection->close();
```

works against a real AMQP 1.0 peer.

Release `v0.0.1`.

------------------------------------------------------------------------

# Months 2--3

Prioritize the vertical messaging path:

``` text
Session
  |
Link
  |
FLOW
  |
TRANSFER
  |
Message
  |
Disposition
```

By the end of this period, target:

``` php
$sender->send($message);

$message = $receiver->receive(timeout: 30);
$message->accept();
```

against multiple implementations.

This is the point where the project becomes useful enough for external
experimentation.

------------------------------------------------------------------------

# Months 4--6

Focus primarily on things early prototypes tend to underinvest in:

-   error behavior
-   link/session edge cases
-   settlement correctness
-   flow control
-   SASL/TLS
-   AMQP message fidelity
-   interoperability
-   malformed input
-   broker restarts
-   memory stability
-   documentation
-   developer ergonomics

Do not prioritize framework integrations over protocol correctness
during this period.

------------------------------------------------------------------------

# Explicit Non-Goals for the Initial MVP

Defer unless required by interoperability:

-   transactions
-   every SASL mechanism
-   framework-specific APIs
-   automatic reconnection magic
-   native acceleration
-   async framework integration
-   WebSocket transport
-   broker management APIs
-   Azure Service Bus-specific management functionality
-   AMQP 0-9-1 compatibility

Keeping these out of the initial scope materially improves the chance of
shipping.

------------------------------------------------------------------------

# Major Technical Risks

## 1. State-machine complexity

**Risk:** obscure ordering and settlement bugs.

**Mitigation:** make the engine deterministic and I/O-independent; test
state transitions directly.

## 2. AMQP type fidelity

**Risk:** PHP's type system collapses distinctions that matter on the
wire.

**Mitigation:** explicit AMQP value objects for ambiguous types.

## 3. Partial input

**Risk:** assuming one socket read equals one AMQP frame.

**Mitigation:** incremental decoder designed for arbitrary byte
boundaries from day one.

## 4. Broker-specific behavior

**Risk:** accidentally implementing "Azure AMQP" or "RabbitMQ AMQP"
rather than AMQP 1.0.

**Mitigation:** test continuously against multiple independent peers and
use the OASIS specification as the authority.

## 5. Async pressure

**Risk:** early blocking design makes later nonblocking support require
a rewrite.

**Mitigation:** protocol engine owns state; transport/driver owns I/O
scheduling.

## 6. Premature public API stability

**Risk:** protocol discoveries force awkward backward compatibility.

**Mitigation:** keep releases pre-1.0 while the protocol model and
ergonomics are being validated.

## 7. Scope explosion

**Risk:** attempting all optional AMQP features before basic
send/receive is robust.

**Mitigation:** release vertical slices and explicitly maintain a
deferred-feature list.

------------------------------------------------------------------------

# Repository Strategy

Suggested structure:

``` text
/
|-- composer.json
|-- README.md
|-- CHANGELOG.md
|-- docs/
|   |-- architecture.md
|   |-- protocol-map.md
|   `-- interoperability.md
|-- resources/
|   `-- spec/
|-- src/
|   |-- Client/
|   |-- Engine/
|   |-- Exception/
|   |-- Protocol/
|   |   |-- Codec/
|   |   |-- Frame/
|   |   |-- Messaging/
|   |   |-- Performative/
|   |   `-- Type/
|   `-- Transport/
|-- tests/
|   |-- Unit/
|   |-- Protocol/
|   |-- Interop/
|   `-- Fixtures/
`-- tools/
```

Keep generated protocol code clearly separated from hand-written code if
generation is used.

------------------------------------------------------------------------

# Definition of Done for Every Iteration

An iteration is complete only when:

1.  implementation exists;
2.  unit/protocol tests exist;
3.  malformed/error cases have been considered;
4.  static analysis passes;
5.  no unexplained memory/resource leak is introduced;
6.  relevant documentation is updated;
7.  interoperability tests are added when the feature crosses the
    network;
8.  the implementation still respects the I/O-independent engine
    boundary.

------------------------------------------------------------------------

# Decision Gates

The project should deliberately stop and evaluate itself at several
points.

## Gate A --- After codec

Ask:

-   Is the PHP implementation understandable and maintainable?
-   Are binary operations fast enough for realistic message sizes?
-   Does the type model feel natural enough in PHP?

Do not optimize without measurements.

## Gate B --- After first connection

Ask:

-   Is the engine/I/O separation working?
-   Can connection behavior be tested without sockets?
-   Are partial reads handled cleanly?

Refactor here if necessary. It will become much more expensive after
links and deliveries exist.

## Gate C --- After first send/receive

Ask:

-   Does the public API feel like PHP rather than a translation of the
    specification?
-   Can advanced AMQP concepts still be expressed?
-   Does the same implementation work against multiple peers?

This is the right point to invite early external users.

## Gate D --- Before v1.0

Ask:

-   Is the API stable enough to support for years?
-   Are failure semantics documented?
-   Are long-running consumers proven stable?
-   Is interoperability broad enough that the package can honestly call
    itself an AMQP 1.0 client rather than a client for one broker?

------------------------------------------------------------------------

# Overall Estimate

For one experienced developer working substantially on the project:

``` text
Specification/architecture       1-2 weeks
Codec                            2-4 weeks
Transport + connection           2-3 weeks
Sessions + links                 2-4 weeks
Send/receive/settlement          3-5 weeks
Messaging + SASL/TLS             3-5 weeks
Hardening/interoperability       6-10 weeks
API/docs/release stabilization   3-5 weeks
```

There will be overlap between these areas.

A reasonable planning range is:

-   **first connection:** \~1 month
-   **useful experimental client:** \~2--3 months
-   **strong beta:** \~4--6 months
-   **credible production-oriented v1:** \~6--9 months

These are planning estimates, not commitments. Protocol interoperability
and edge cases are likely to dominate the uncertainty.

------------------------------------------------------------------------

# Immediate Next Step

Do **not** begin by implementing `Connection`, `Sender`, or Azure
Service Bus support.

Start with a two-week codec/engine spike whose success criteria are:

``` text
1. Decode representative AMQP 1.0 values.
2. Encode them to independently verified bytes.
3. Decode and encode OPEN.
4. Parse fragmented AMQP frames incrementally.
5. Feed frames into an I/O-independent engine.
```

Then spend the next two weeks reaching:

``` php
$connection = Connection::connect($dsn);
$connection->close();
```

against a real broker.

That first month will validate the most important architectural
assumptions while the codebase is still cheap to change.
