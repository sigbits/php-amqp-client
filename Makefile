PHP_VERSION ?= 8.3
DOCKER_COMPOSE ?= docker compose
DOCKER_RUN = PHP_VERSION=$(PHP_VERSION) $(DOCKER_COMPOSE) run --rm php
BROKER_COMPOSE = PHP_VERSION=$(PHP_VERSION) $(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.broker.yml
BROKER_READY_TIMEOUT ?= 60
LONG_TEST_CYCLES ?= 100
LONG_TEST_CREDIT_CYCLES ?= 20
LONG_TEST_CREDIT_WINDOW ?= 2
LONG_TEST_FAILURE_CYCLES ?= 3
LONG_TEST_LARGE_MESSAGE_BYTES ?= 4096
LONG_TEST_LARGE_MESSAGE_CYCLES ?= 5
LONG_TEST_MAX_MEMORY_GROWTH_BYTES ?= 8388608
LONG_TEST_RECONNECT_CYCLES ?= 25
LONG_TEST_RECEIVE_CYCLES ?= $(LONG_TEST_CYCLES)
LONG_TEST_REQUEST_REPLY_CYCLES ?= $(LONG_TEST_CYCLES)
LONG_TEST_SEND_CYCLES ?= $(LONG_TEST_CYCLES)
SOAK_PROFILE ?= all

.PHONY: build install validate test test-integration test-security test-long test-soak test-broker-restart broker-up broker-wait broker-down broker-reset cs cs-fix stan proof-connection ci shell

build:
	PHP_VERSION=$(PHP_VERSION) $(DOCKER_COMPOSE) build php

install:
	$(DOCKER_RUN) composer install

validate:
	$(DOCKER_RUN) composer validate --strict --no-check-lock

test:
	$(DOCKER_RUN) composer test

test-integration: broker-reset
	$(BROKER_COMPOSE) run --rm -e RUN_BROKER_TESTS=1 php composer test:integration

test-security: broker-reset
	$(BROKER_COMPOSE) run --rm -e RUN_BROKER_SECURITY_TESTS=1 -e AMQP_ARTEMIS_TLS_URI=amqps://guest:guest@artemis-tls:5671 -e AMQP_ARTEMIS_TLS_INVALID_URI=amqps://guest:wrong@artemis-tls:5671 -e AMQP_ARTEMIS_TLS_CA_FILE=/app/docker/broker/tls/ca.crt -e AMQP_ARTEMIS_TLS_PEER_NAME=artemis-tls.sigbits.test -e AMQP_RABBITMQ_TLS_URI=amqps://guest:guest@rabbitmq-tls:5671 -e AMQP_RABBITMQ_TLS_INVALID_URI=amqps://guest:wrong@rabbitmq-tls:5671 -e AMQP_RABBITMQ_TLS_CA_FILE=/app/docker/broker/tls/ca.crt -e AMQP_RABBITMQ_TLS_PEER_NAME=rabbitmq-tls.sigbits.test php composer test:integration -- --filter TlsSaslBrokerTest

test-long: broker-reset
	$(BROKER_COMPOSE) run --rm -e RUN_LONG_TESTS=1 -e AMQP_TOXIPROXY_API=http://toxiproxy:8474 -e AMQP_LONG_CYCLES=$(LONG_TEST_CYCLES) -e AMQP_LONG_CREDIT_CYCLES=$(LONG_TEST_CREDIT_CYCLES) -e AMQP_LONG_CREDIT_WINDOW=$(LONG_TEST_CREDIT_WINDOW) -e AMQP_LONG_FAILURE_CYCLES=$(LONG_TEST_FAILURE_CYCLES) -e AMQP_LONG_LARGE_MESSAGE_BYTES=$(LONG_TEST_LARGE_MESSAGE_BYTES) -e AMQP_LONG_LARGE_MESSAGE_CYCLES=$(LONG_TEST_LARGE_MESSAGE_CYCLES) -e AMQP_LONG_MAX_MEMORY_GROWTH_BYTES=$(LONG_TEST_MAX_MEMORY_GROWTH_BYTES) -e AMQP_LONG_RECONNECT_CYCLES=$(LONG_TEST_RECONNECT_CYCLES) php composer test:long

test-soak: broker-reset
	LONG_TEST_CYCLES=$(LONG_TEST_CYCLES) LONG_TEST_CREDIT_CYCLES=$(LONG_TEST_CREDIT_CYCLES) LONG_TEST_CREDIT_WINDOW=$(LONG_TEST_CREDIT_WINDOW) LONG_TEST_LARGE_MESSAGE_BYTES=$(LONG_TEST_LARGE_MESSAGE_BYTES) LONG_TEST_LARGE_MESSAGE_CYCLES=$(LONG_TEST_LARGE_MESSAGE_CYCLES) LONG_TEST_MAX_MEMORY_GROWTH_BYTES=$(LONG_TEST_MAX_MEMORY_GROWTH_BYTES) LONG_TEST_RECEIVE_CYCLES=$(LONG_TEST_RECEIVE_CYCLES) LONG_TEST_RECONNECT_CYCLES=$(LONG_TEST_RECONNECT_CYCLES) LONG_TEST_REQUEST_REPLY_CYCLES=$(LONG_TEST_REQUEST_REPLY_CYCLES) LONG_TEST_SEND_CYCLES=$(LONG_TEST_SEND_CYCLES) SOAK_PROFILE=$(SOAK_PROFILE) ./tools/long-profile.sh $(SOAK_PROFILE)

test-broker-restart: broker-reset
	./tools/broker-restart.sh testPublicReceiverObservesBrokerRestartAgainstArtemis
	./tools/broker-restart.sh testPublicSenderObservesBrokerRestartAgainstArtemis
	./tools/broker-restart.sh testPublicSettlementObservesBrokerRestartAgainstArtemis

broker-up:
	$(BROKER_COMPOSE) up -d qpid artemis artemis-tls rabbitmq rabbitmq-tls servicebus-sql servicebus-emulator toxiproxy
	@$(MAKE) broker-wait

broker-wait:
	@i=0; until $(BROKER_COMPOSE) logs --no-color qpid | grep -q 'Qpid Broker Ready'; do i=$$((i + 1)); if [ $$i -ge $(BROKER_READY_TIMEOUT) ]; then echo 'Timed out waiting for Qpid Broker-J readiness.' >&2; exit 1; fi; sleep 1; done; echo 'Qpid Broker-J ready.'
	@i=0; until $(BROKER_COMPOSE) logs --no-color artemis | grep -q 'Server is now active'; do i=$$((i + 1)); if [ $$i -ge $(BROKER_READY_TIMEOUT) ]; then echo 'Timed out waiting for ActiveMQ Artemis readiness.' >&2; exit 1; fi; sleep 1; done; echo 'ActiveMQ Artemis ready.'
	@i=0; until $(BROKER_COMPOSE) logs --no-color artemis-tls | grep -q 'Server artemis_amqp/artemis is UP'; do i=$$((i + 1)); if [ $$i -ge $(BROKER_READY_TIMEOUT) ]; then echo 'Timed out waiting for ActiveMQ Artemis TLS endpoint readiness.' >&2; exit 1; fi; sleep 1; done; echo 'ActiveMQ Artemis TLS endpoint ready.'
	@i=0; until $(BROKER_COMPOSE) exec -T rabbitmq rabbitmq-diagnostics -q ping >/dev/null 2>&1; do i=$$((i + 1)); if [ $$i -ge $(BROKER_READY_TIMEOUT) ]; then echo 'Timed out waiting for RabbitMQ readiness.' >&2; exit 1; fi; sleep 1; done; echo 'RabbitMQ ready.'
	@i=0; until $(BROKER_COMPOSE) logs --no-color rabbitmq-tls | grep -q 'Server rabbitmq_amqp/rabbitmq is UP'; do i=$$((i + 1)); if [ $$i -ge $(BROKER_READY_TIMEOUT) ]; then echo 'Timed out waiting for RabbitMQ TLS endpoint readiness.' >&2; exit 1; fi; sleep 1; done; echo 'RabbitMQ TLS endpoint ready.'
	@i=0; until $(BROKER_COMPOSE) logs --no-color servicebus-emulator | grep -q 'Emulator Service is Successfully Up'; do i=$$((i + 1)); if [ $$i -ge $(BROKER_READY_TIMEOUT) ]; then echo 'Timed out waiting for Azure Service Bus emulator readiness.' >&2; exit 1; fi; sleep 1; done; echo 'Azure Service Bus emulator ready.'

broker-down:
	$(BROKER_COMPOSE) stop qpid artemis artemis-tls rabbitmq rabbitmq-tls servicebus-emulator servicebus-sql toxiproxy
	$(BROKER_COMPOSE) rm --force --volumes qpid artemis artemis-tls rabbitmq rabbitmq-tls servicebus-emulator servicebus-sql toxiproxy

broker-reset:
	@$(MAKE) broker-down
	@$(MAKE) broker-up

cs:
	$(DOCKER_RUN) composer cs

cs-fix:
	$(DOCKER_RUN) composer cs:fix

stan:
	$(DOCKER_RUN) composer stan

proof-connection:
	$(DOCKER_RUN) composer proof:connection

ci:
	$(DOCKER_RUN) composer validate --strict --no-check-lock
	$(DOCKER_RUN) composer install
	$(DOCKER_RUN) composer cs
	$(DOCKER_RUN) composer stan
	$(DOCKER_RUN) composer test

shell:
	$(DOCKER_RUN) bash
