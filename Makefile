PHP_VERSION ?= 8.3
DOCKER_COMPOSE ?= docker compose
DOCKER_RUN = PHP_VERSION=$(PHP_VERSION) $(DOCKER_COMPOSE) run --rm php

.PHONY: build install validate test cs cs-fix stan proof-connection ci shell

build:
	PHP_VERSION=$(PHP_VERSION) $(DOCKER_COMPOSE) build php

install:
	$(DOCKER_RUN) composer install

validate:
	$(DOCKER_RUN) composer validate --strict --no-check-lock

test:
	$(DOCKER_RUN) composer test

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
