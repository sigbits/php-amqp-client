#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

PHP_VERSION="${PHP_VERSION:-8.3}"
DOCKER_COMPOSE="${DOCKER_COMPOSE:-docker compose}"
# shellcheck disable=SC2206
DOCKER_COMPOSE_CMD=($DOCKER_COMPOSE)
BROKER_COMPOSE=(env "PHP_VERSION=$PHP_VERSION" "${DOCKER_COMPOSE_CMD[@]}" -f docker-compose.yml -f docker-compose.broker.yml)
SIGNAL_DIR=".phpunit.cache/broker-restart"
READY_FILE="$SIGNAL_DIR/artemis-ready"
CONTINUE_FILE="$SIGNAL_DIR/artemis-continue"
LOG_FILE="$SIGNAL_DIR/phpunit.log"

mkdir -p "$SIGNAL_DIR"
rm -f "$READY_FILE" "$CONTINUE_FILE" "$LOG_FILE"

test_pid=""

cleanup() {
    if [[ -n "$test_pid" ]] && kill -0 "$test_pid" 2>/dev/null; then
        kill "$test_pid" 2>/dev/null || true
        wait "$test_pid" 2>/dev/null || true
    fi
}

trap cleanup EXIT

"${BROKER_COMPOSE[@]}" run --rm \
    -e RUN_LONG_TESTS=1 \
    -e AMQP_BROKER_RESTART_READY_FILE="/app/$READY_FILE" \
    -e AMQP_BROKER_RESTART_CONTINUE_FILE="/app/$CONTINUE_FILE" \
    php vendor/bin/phpunit --configuration phpunit.xml.dist \
    tests/LongRunning/PublicWorkerLifecycleTest.php \
    --filter testPublicReceiverObservesBrokerRestartAgainstArtemis \
    >"$LOG_FILE" 2>&1 &
test_pid="$!"

deadline=$((SECONDS + 30))

while [[ ! -f "$READY_FILE" ]]; do
    if ! kill -0 "$test_pid" 2>/dev/null; then
        cat "$LOG_FILE"
        wait "$test_pid"
    fi

    if (( SECONDS >= deadline )); then
        cat "$LOG_FILE" 2>/dev/null || true
        echo "Timed out waiting for restart test readiness." >&2
        exit 1
    fi

    sleep 0.1
done

"${BROKER_COMPOSE[@]}" restart artemis
sleep 2
touch "$CONTINUE_FILE"

if ! wait "$test_pid"; then
    cat "$LOG_FILE"
    exit 1
fi

trap - EXIT
cat "$LOG_FILE"
