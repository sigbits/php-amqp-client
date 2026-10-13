# Broker Compatibility Notes

## Supported Local Matrix

The Docker broker stack currently provides:

- Apache Qpid Broker-J on container port `5672`, exposed on host port `56720`,
  plus TLS endpoint `qpid-tls` on container port `5671`, exposed on host port
  `56721`.
- Apache ActiveMQ Artemis on container port `5672`, exposed on host port
  `56730`, plus TLS endpoint `artemis-tls` on container port `5671`, exposed
  on host port `56731`.
- RabbitMQ 4 on container port `5672`, exposed on host port `56740`, plus TLS
  endpoint `rabbitmq-tls` on container port `5671`, exposed on host port
  `56741`.
- Azure Service Bus emulator on container port `5672`, exposed on host port
  `56750`, plus health and management endpoint on container port `5300`, exposed
  on host port `58300`.

`make test-integration`, `make test-security`, `make test-long`, and
`make test-soak` recreate the broker stack before running so stale broker state
cannot affect the result.

`make test-security` runs the broker TLS/SASL hardening suite. It currently
covers Apache Qpid Broker-J, ActiveMQ Artemis, and RabbitMQ 4 through local TLS
endpoints, which terminate TLS with HAProxy and forward AMQP traffic to each
broker container.
The suite verifies trusted certificate validation, rejected untrusted
certificate validation, and rejected invalid SASL PLAIN credentials for these
brokers. Test certificates live under `docker/broker/tls` and are intended only
for local broker integration tests. Qpid Broker-J TLS/SASL security coverage
uses the `qpid-tls` endpoint.

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

The local RabbitMQ matrix also covers exchange/routing-key sending through
`/exchanges/:exchange/:routing-key` by binding a random test queue to
`amq.topic`, sending through the exchange address, and receiving the routed
message from the queue address.

RabbitMQ settlement-effect coverage verifies that accepted and rejected
deliveries leave no ready message in the queue, while released deliveries become
available again for redelivery.

## Qpid Broker-J

Qpid Broker-J is used for handshake, public connection/session lifecycle,
public sender/receiver detach, send, receive, settlement effects, and
long-running worker soak-profile coverage. Tests create explicit queues through
the broker management API because this test broker does not auto-create random
targets. Qpid settlement-effect coverage verifies that accepted deliveries leave
no available message, released deliveries become available again, and rejected
deliveries are made available again for redelivery by this broker. Qpid Broker-J
TLS/SASL security hardening is covered through the local `qpid-tls` HAProxy
endpoint.

## ActiveMQ Artemis

ActiveMQ Artemis is used for handshake, public connection/session lifecycle,
public sender/receiver detach, send, receive, accepted settlement, TLS/SASL
hardening through `artemis-tls`, long-running worker, send-only, receive-only,
request/reply-like, bounded credit, reconnect, transport interruption, broker
restart, large DATA message coverage, and repeated DATA/AMQP sequence body
round-trip regressions.

ActiveMQ Artemis settlement-effect coverage verifies that accepted and rejected
deliveries leave no message on the address, while released deliveries become
available again for redelivery.

`make test-broker-restart` currently exercises ActiveMQ Artemis restart during
active public receiver, sender, and settlement loops. The harness runs PHPUnit
inside Docker and performs the actual broker container restart from the
host-side Docker Compose process.

## Azure Service Bus Emulator

Azure Service Bus emulator is used for local AMQP TCP on `servicebus-emulator:5672`.
The emulator depends on the local `servicebus-sql` SQL Server container and loads static queue definitions from
`docker/broker/servicebus-emulator-config.json`.

The local emulator matrix covers AMQP/SASL handshake, public
connection/session lifecycle, public send, public receive, and accepted-delivery
settlement against configured queues such as `sigbits.public.accept`.

This coverage proves compatibility with the local development emulator only. It
does not claim production Azure Service Bus cloud coverage, AMQP over
WebSockets, Entra ID authentication, VNet/private endpoint behavior, cloud
quotas, broker persistence across emulator restart, or cloud SLA behavior.

`make test-azure-cloud` runs a real Azure Service Bus cloud smoke test and
requires explicit opt-in credentials provided through
`AMQP_AZURE_SERVICE_BUS_CLOUD_URI` and
`AMQP_AZURE_SERVICE_BUS_CLOUD_ADDRESS`. The smoke test sends, receives, and
accepts one message against the configured address. It is intentionally outside
the default local broker matrix and requires a dedicated test queue.
