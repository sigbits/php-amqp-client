# Broker Compatibility Notes

## Supported Local Matrix

The Docker broker stack currently provides:

- Apache Qpid Broker-J on container port `5672`, exposed on host port `56720`.
- Apache ActiveMQ Artemis on container port `5672`, exposed on host port
  `56730`.
- RabbitMQ 4 on container port `5672`, exposed on host port `56740`.

`make test-integration` and `make test-long` recreate the broker stack before
running so stale broker state cannot affect the result.

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
public sender/receiver detach, send, receive, and accepted settlement coverage.
Tests create explicit queues through the broker management API because this
test broker does not auto-create random targets.

## ActiveMQ Artemis

ActiveMQ Artemis is used for handshake, public connection/session lifecycle,
public sender/receiver detach, send, receive, accepted settlement, long-running
worker, bounded credit, reconnect, transport interruption, and large DATA
message coverage.
