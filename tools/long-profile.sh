#!/usr/bin/env bash
set -euo pipefail

profile="${1:-${SOAK_PROFILE:-all}}"

case "$profile" in
    all)
        filter='testRepeatedPublic(SendOnlyCycles|ReceiveOnlyCycles|RequestReplyLikeCycles|CreditWindowCycles|ReconnectMessageCycles)DoNotLeakMemory|testFragmentedLargePublicMessagesRoundTrip'
        ;;
    send-only)
        filter='testRepeatedPublicSendOnlyCyclesDoNotLeakMemory'
        ;;
    receive-only)
        filter='testRepeatedPublicReceiveOnlyCyclesDoNotLeakMemory'
        ;;
    request-reply)
        filter='testRepeatedPublicRequestReplyLikeCyclesDoNotLeakMemory'
        ;;
    bounded-credit)
        filter='testRepeatedPublicCreditWindowCyclesDoNotLeakMemory'
        ;;
    reconnect)
        filter='testRepeatedPublicReconnectMessageCyclesDoNotLeakMemory'
        ;;
    large-message)
        filter='testFragmentedLargePublicMessagesRoundTrip'
        ;;
    *)
        echo "Unknown soak profile: ${profile}" >&2
        echo "Supported profiles: all, send-only, receive-only, request-reply, bounded-credit, reconnect, large-message" >&2
        exit 2
        ;;
esac

docker_compose="${DOCKER_COMPOSE:-docker compose}"
read -r -a docker_compose_parts <<< "$docker_compose"

exec env PHP_VERSION="${PHP_VERSION:-8.3}" "${docker_compose_parts[@]}" \
    -f docker-compose.yml \
    -f docker-compose.broker.yml \
    run --rm \
    -e RUN_LONG_TESTS=1 \
    -e AMQP_LONG_PROFILE="$profile" \
    -e AMQP_LONG_CYCLES="${LONG_TEST_CYCLES:-100}" \
    -e AMQP_LONG_CREDIT_CYCLES="${LONG_TEST_CREDIT_CYCLES:-20}" \
    -e AMQP_LONG_CREDIT_WINDOW="${LONG_TEST_CREDIT_WINDOW:-2}" \
    -e AMQP_LONG_LARGE_MESSAGE_BYTES="${LONG_TEST_LARGE_MESSAGE_BYTES:-4096}" \
    -e AMQP_LONG_LARGE_MESSAGE_CYCLES="${LONG_TEST_LARGE_MESSAGE_CYCLES:-5}" \
    -e AMQP_LONG_MAX_MEMORY_GROWTH_BYTES="${LONG_TEST_MAX_MEMORY_GROWTH_BYTES:-8388608}" \
    -e AMQP_LONG_RECEIVE_CYCLES="${LONG_TEST_RECEIVE_CYCLES:-${LONG_TEST_CYCLES:-100}}" \
    -e AMQP_LONG_RECONNECT_CYCLES="${LONG_TEST_RECONNECT_CYCLES:-25}" \
    -e AMQP_LONG_REQUEST_REPLY_CYCLES="${LONG_TEST_REQUEST_REPLY_CYCLES:-${LONG_TEST_CYCLES:-100}}" \
    -e AMQP_LONG_SEND_CYCLES="${LONG_TEST_SEND_CYCLES:-${LONG_TEST_CYCLES:-100}}" \
    php composer test:long -- --filter "$filter"
