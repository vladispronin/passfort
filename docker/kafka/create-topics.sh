#!/bin/bash

KAFKA_HOST="kafka:29092"

echo "Creating Kafka topics..."

kafka-topics --create \
    --bootstrap-server $KAFKA_HOST \
    --replication-factor 1 \
    --partitions 3 \
    --topic passfort.security.logs \
    --if-not-exists

kafka-topics --create \
    --bootstrap-server $KAFKA_HOST \
    --replication-factor 1 \
    --partitions 2 \
    --topic passfort.notifications.email \
    --if-not-exists

kafka-topics --create \
    --bootstrap-server $KAFKA_HOST \
    --replication-factor 1 \
    --partitions 3 \
    --topic passfort.vault.events \
    --if-not-exists

echo "Kafka topics created successfully!"
kafka-topics --list --bootstrap-server $KAFKA_HOST
