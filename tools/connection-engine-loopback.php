<?php

declare(strict_types=1);

use Sigbits\Amqp\Engine\ConnectionEngine;

require dirname(__DIR__) . '/vendor/autoload.php';

$client = new ConnectionEngine(localContainerId: 'client');
$server = new ConnectionEngine(localContainerId: 'server');

$clientBytes = implode('', $client->start());
$serverBytes = implode('', $server->start());

$server->push($clientBytes);
$client->push($serverBytes);

echo 'client: ' . $client->state()->name . PHP_EOL;
echo 'server: ' . $server->state()->name . PHP_EOL;

$clientCloseBytes = implode('', $client->close());
$serverCloseBytes = implode('', $server->close());

$server->push($clientCloseBytes);
$client->push($serverCloseBytes);

echo 'client: ' . $client->state()->name . PHP_EOL;
echo 'server: ' . $server->state()->name . PHP_EOL;
