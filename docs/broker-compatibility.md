# Broker Compatibility Notes

## Supported Local Matrix

The Docker broker stack currently provides:

- Apache Qpid Broker-J on container port `5672`, exposed on host port `56720`.
- Apache ActiveMQ Artemis on container port `5672`, exposed on host port
  `56730`.
- RabbitMQ 4 on container port `5672`, exposed on host port `56740`.

`make test-integration`, `make test-security`, `make test-long`, and
`make test-soak` recreate the broker stack before running so stale broker state
cannot affect the result.

`make test-security` runs the broker TLS/SASL hardening suite. It currently
covers ActiveMQ Artemis through the local `artemis-tls` endpoint, which
terminates TLS with HAProxy and forwards AMQP traffic to the Artemis container.
The suite verifies trusted certificate validation, rejected untrusted
certificate validation, and rejected invalid SASL PLAIN credentials. Test
certificates live under `docker/broker/tls` and are intended only for local
broker integration tests.

`make test-soak` runs configurable long-running worker profiles inside Docker.
Use `SOAK_PROFILE` to select `all`, `send-only`, `receive-only`,
`request-reply`, `bounded-credit`, `reconnect`, or `large-message`.
Profile-specific cycle knobs are available through `LONG_TEST_SEND_CYCLES`,
`LONG_TEST_RECEIVE_CYCLES`, `LONG_TEST_REQUEST_REPLY_CYCLES`,
`LONG_TEST_CREDIT_CYCLES`, `LONG_TEST_RECONNECT_CYCLES`, and
`LONG_TEST_LARGE_MESSAGE_CYCLES`.

## RabbitMQ 4 AMQP 1.0

RabbitMQ 4 supports AMQP 1.0 natively on the standard AMQP listener. The local
test matrix uses the `rabbitmq:4-management` image with the `guest` user and
password.

Initial v0.9.0 coverage adds RabbitMQ to the AMQP/SASL handshake, public
connection/session mapping, public sender matrix, public receiver matrix, and
public accepted-delivery settlement matrix, and public sender/receiver detach
matrix. Public sender/receiver/settlement/detach coverage creates a durable
random test queue through the RabbitMQ management API and targets it with the
AMQP 1.0 address v2 queue form `/queues/:queue`.

The local RabbitMQ coverage uses queue addresses. Exchange/routing-key addresses
such as `/exchanges/:exchange/:routing-key` are not yet part of the local
RabbitMQ matrix.

## Qpid Broker-J

Qpid Broker-J is used for handshake, public connection/session lifecycle,
public sender/receiver detach, send, receive, accepted settlement, and
long-running worker soak-profile coverage. Tests create explicit queues through
the broker management API because this test broker does not auto-create random
targets.

## ActiveMQ Artemis

ActiveMQ Artemis is used for handshake, public connection/session lifecycle,
public sender/receiver detach, send, receive, accepted settlement, TLS/SASL
hardening, long-running worker, send-only, receive-only, request/reply-like,
bounded credit, reconnect, transport interruption, broker restart, and large
DATA message coverage.

`make test-broker-restart` currently exercises ActiveMQ Artemis restart during
active public receiver, sender, and settlement loops. The harness runs PHPUnit
inside Docker and performs the actual broker container restart from the
host-side Docker Compose process.
