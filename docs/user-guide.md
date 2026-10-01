# User Guide

This guide covers the supported synchronous public API for the current
development line. The API is still pre-1.0 until the release readiness gate is
closed, but these workflows are the intended v1.0.0 user-facing surface.

### Installation

Install the package with Composer:

```sh
composer require sigbits/php-amqp-client
```

The package requires PHP 8.3 or newer. No PHP extension, FFI binding, sidecar
process, or external AMQP client binary is required.

### Connection Configuration

Open a connection with `Connection::connect()`:

```php
<?php

use Sigbits\Amqp\Client\Connection;

$connection = Connection::connect(
    uri: 'amqp://guest:guest@localhost:5672',
    containerId: 'orders-worker',
    timeoutSeconds: 10.0,
);

$session = $connection->beginSession();
```

Connection URIs support `amqp://` for TCP and `amqps://` for TLS. When the URI
contains a username, the client uses SASL PLAIN with the URI password. Without a
username, the client uses SASL ANONYMOUS.

`timeoutSeconds` is a floating-point number of seconds. It is used for opening
the stream and for blocking protocol reads performed by the public connection,
session, sender, and receiver workflows.

Close resources explicitly when the application is finished:

```php
$session->end();
$connection->close();
```

`Connection::close()` and `Session::end()` are idempotent after their lifecycle
has completed.

### Sending Messages

Open a sender from a session, then send either a string or a `Message` object:

```php
<?php

use Sigbits\Amqp\Client\Connection;

$connection = Connection::connect('amqp://guest:guest@localhost:5672');
$session = $connection->beginSession();
$sender = $session->openSender('/queues/orders');

$sender->send('order-created');

$sender->detach();
$session->end();
$connection->close();
```

A string is sent as a DATA-body message. Use a `Message` object when the
application needs message properties, headers, annotations, AMQP value bodies,
or AMQP sequence bodies:

```php
<?php

use Sigbits\Amqp\Protocol\Message\Message;
use Sigbits\Amqp\Protocol\Message\Properties;

$sender->send(new Message(
    body: '{"id":"order-123"}',
    properties: new Properties(
        messageId: 'order-123',
        contentType: 'application/json',
        subject: 'order-created',
    ),
    applicationProperties: [
        'source' => 'checkout',
    ],
));
```

Supported map values are strings and signed AMQP scalar wrappers. String map
keys must be non-empty and fit in the AMQP symbol8 encoding. AMQP value bodies,
sequence items, and string property values must fit in the documented string8
limits.

### Receiving Messages

Open a receiver from a session and call `receive()` with a timeout in
milliseconds:

```php
<?php

use Sigbits\Amqp\Client\Connection;

$connection = Connection::connect('amqp://guest:guest@localhost:5672');
$session = $connection->beginSession();
$receiver = $session->openReceiver('/queues/orders', credit: 10);

$message = $receiver->receive(1_000);

if ($message !== null) {
    echo $message->body;
}
```

`Receiver::receive($timeoutMilliseconds)` returns `null` when no message is
available before the receive deadline. A timeout of `0` performs a non-blocking
poll of already buffered receiver state plus any immediate read attempt.
Negative timeout values are treated as already expired unless a delivery is
already buffered.

### Delivery Settlement

Use `receiveDelivery()` when the application needs to settle a delivery
explicitly:

```php
$delivery = $receiver->receiveDelivery(1_000);

if ($delivery !== null) {
    try {
        process($delivery->message());
        $delivery->accept();
    } catch (\Throwable $exception) {
        $delivery->release();
    }
}
```

Each `Delivery` object can be settled once with `accept()`, `release()`, or
`reject()`. A second settlement attempt raises `ClientException`. If the receiver
link is detached before settlement, settlement raises `ClientException`.

### Receiver Credit Management

Receiver credit is caller-managed. `Session::openReceiver()` grants the initial
credit window; the default is `1`.

For bounded workers, grant more credit after the application has handled earlier
deliveries:

```php
$receiver = $session->openReceiver('/queues/orders', credit: 5);

while (true) {
    $delivery = $receiver->receiveDelivery(1_000);

    if ($delivery === null) {
        continue;
    }

    process($delivery->message());
    $delivery->accept();

    $receiver->grantCredit(1);
}
```

The client does not automatically replenish receiver credit. A credit value of
`0` advertises no additional credit. Negative values are outside the supported
public contract.

### TLS

Use `amqps://` to open a TLS stream. The default AMQPS port is `5671` when the
URI does not include a port.

```php
<?php

use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Transport\TlsOptions;

$connection = Connection::connect(
    uri: 'amqps://guest:guest@broker.example.com',
    tls: new TlsOptions(
        cafile: '/etc/ssl/certs/broker-ca.pem',
        peerName: 'broker.example.com',
    ),
);
```

`TlsOptions` maps to PHP stream SSL context options. Peer and peer-name
verification are enabled by default. Use `localCert` when the broker requires a
client certificate.

### SASL

By default, a URI with credentials uses SASL PLAIN:

```php
$connection = Connection::connect('amqp://worker:s3cret@localhost:5672');
```

A URI without credentials uses SASL ANONYMOUS:

```php
$connection = Connection::connect('amqp://localhost:5672');
```

You can pass an explicit SASL client when the credentials should not be encoded
in the URI:

```php
<?php

use Sigbits\Amqp\Client\Connection;
use Sigbits\Amqp\Protocol\Sasl\SaslClient;

$connection = Connection::connect(
    uri: 'amqp://localhost:5672',
    saslClient: SaslClient::plain('worker', 's3cret'),
);
```

Supported mechanisms are ANONYMOUS and PLAIN.

### Retry Boundaries

Public workflows raise `Sigbits\Amqp\Client\ClientException` for client
lifecycle failures and `Sigbits\Amqp\Transport\TransportException` for stream
and URI failures.

Use these retry boundaries:

- `TransportException::connectionFailed()` means no AMQP connection was opened.
  Retry by creating a new connection after applying application backoff.
- `TransportException::unexpectedEndOfStream()`,
  `TransportException::readTimedOut()`, and
  `TransportException::writeFailed()` mean stream state is uncertain. Close the
  current connection and retry on a fresh connection if the operation is
  idempotent at the application level.
- `ClientException::remoteConnectionClosed()` means the peer closed the AMQP
  connection. Reopen the connection before retrying.
- `ClientException::remoteSessionEnded()` means the peer ended the session.
  Begin a new session before retrying link operations.
- Sender or receiver detach exceptions mean the link is no longer usable. Open a
  new link before retrying.
- `ClientException::senderLinkCreditExhausted()` means sending could not obtain
  credit before the underlying wait failed. Retry only after reopening the link
  or otherwise confirming credit is available.
- Settlement failures must not be retried against the same `Delivery` object.

### Broker Interoperability

The local Docker broker matrix covers Apache ActiveMQ Artemis, Apache Qpid
Broker-J, and RabbitMQ 4 for core public client workflows.

Broker-specific notes:

- ActiveMQ Artemis is covered for handshake, public connection/session
  lifecycle, sender/receiver detach, send, receive, settlement, TLS/SASL
  hardening, long-running worker profiles, transport interruption, broker
  restart, and large DATA messages.
- Qpid Broker-J is covered for standard AMQP public workflows. Tests create
  queues through the broker management API because the local Qpid Broker-J
  container does not auto-create random targets. Qpid Broker-J TLS/SASL coverage
  is not wired into the local matrix.
- RabbitMQ 4 coverage uses queue address v2 semantics with `/queues/:queue`.
  Exchange/routing-key address forms are not part of the current local matrix.
- Azure Service Bus emulator coverage uses the local development emulator over
  AMQP TCP with static queues configured in
  `docker/broker/servicebus-emulator-config.json`. This does not claim
  production Azure Service Bus cloud coverage.

See `docs/broker-compatibility.md` for the current broker matrix, local ports,
TLS endpoint notes, and known limitations.

### Long-Running Worker Operation

Long-running workers should:

- create fresh connections after transport loss or remote connection close;
- create fresh sessions after remote session end;
- open fresh links after sender or receiver detach;
- explicitly settle deliveries according to application policy;
- explicitly replenish receiver credit with `Receiver::grantCredit()`;
- close sessions and connections during orderly shutdown.

The local hardening suite includes repeated lifecycle, send, receive,
settlement, bounded-credit, reconnect, transport-interruption, and large-message
profiles.

Run the Docker-first worker hardening checks with:

```sh
make test-long
make test-soak SOAK_PROFILE=all
make broker-down
```

Use the `LONG_TEST_*` environment variables documented in `README.md` to shorten
or expand profile cycles for local verification.
