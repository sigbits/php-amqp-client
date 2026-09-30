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

.PHONY: build install validate test test-integration test-long broker-up broker-wait broker-down cs cs-fix stan proof-connection ci shell

build:
	PHP_VERSION=$(PHP_VERSION) $(DOCKER_COMPOSE) build php

install:
	$(DOCKER_RUN) composer install

validate:
	$(DOCKER_RUN) composer validate --strict --no-check-lock

test:
	$(DOCKER_RUN) composer test

test-integration: broker-up
	$(BROKER_COMPOSE) run --rm -e RUN_BROKER_TESTS=1 php composer test:integration

test-long: broker-up
	$(BROKER_COMPOSE) run --rm -e RUN_LONG_TESTS=1 -e AMQP_TOXIPROXY_API=http://toxiproxy:8474 -e AMQP_LONG_CYCLES=$(LONG_TEST_CYCLES) -e AMQP_LONG_CREDIT_CYCLES=$(LONG_TEST_CREDIT_CYCLES) -e AMQP_LONG_CREDIT_WINDOW=$(LONG_TEST_CREDIT_WINDOW) -e AMQP_LONG_FAILURE_CYCLES=$(LONG_TEST_FAILURE_CYCLES) -e AMQP_LONG_LARGE_MESSAGE_BYTES=$(LONG_TEST_LARGE_MESSAGE_BYTES) -e AMQP_LONG_LARGE_MESSAGE_CYCLES=$(LONG_TEST_LARGE_MESSAGE_CYCLES) -e AMQP_LONG_MAX_MEMORY_GROWTH_BYTES=$(LONG_TEST_MAX_MEMORY_GROWTH_BYTES) -e AMQP_LONG_RECONNECT_CYCLES=$(LONG_TEST_RECONNECT_CYCLES) php composer test:long

broker-up:
	$(BROKER_COMPOSE) up -d qpid artemis toxiproxy
	@$(MAKE) broker-wait

broker-wait:
	@i=0; until $(BROKER_COMPOSE) logs --no-color qpid | grep -q 'Qpid Broker Ready'; do i=$$((i + 1)); if [ $$i -ge $(BROKER_READY_TIMEOUT) ]; then echo 'Timed out waiting for Qpid Broker-J readiness.' >&2; exit 1; fi; sleep 1; done; echo 'Qpid Broker-J ready.'
	@i=0; until $(BROKER_COMPOSE) logs --no-color artemis | grep -q 'Server is now active'; do i=$$((i + 1)); if [ $$i -ge $(BROKER_READY_TIMEOUT) ]; then echo 'Timed out waiting for ActiveMQ Artemis readiness.' >&2; exit 1; fi; sleep 1; done; echo 'ActiveMQ Artemis ready.'

broker-down:
	$(BROKER_COMPOSE) stop qpid artemis toxiproxy
	$(BROKER_COMPOSE) rm --force --volumes qpid artemis toxiproxy

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
