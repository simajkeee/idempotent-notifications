# Notification Service

Laravel notification service for asynchronously sending bulk Email and SMS notifications.

The service uses:

- PostgreSQL for notification and idempotency records
- RabbitMQ for persistent queues
- Redis for request deduplication and job locks
- Separate high-priority and default notification queues

## Requirements

- Docker with Docker Compose
- Composer

## Setup

Install dependencies and create the environment file:

```bash
composer install
cp .env.example .env
```

Configure these values in `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=smart_logistics
DB_USERNAME=sail
DB_PASSWORD=password

CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=default
RABBITMQ_WORKER=default
```

Start the application, PostgreSQL, Redis, RabbitMQ, and queue worker:

```bash
make up
```

Generate the application key and initialize the database:

```bash
make key
make fresh
```

The API is available at `http://localhost/api`.

RabbitMQ management is available at `http://localhost:15672` using the credentials configured in `.env`.

Common commands are available through the Makefile:

```bash
make help
make up
make fresh
make test
make test-integration
make down
```

## Queue Processing

The Compose queue worker consumes queues in priority order:

```text
notifications.high,notifications.default
```

- `transactional` notifications use `notifications.high`
- `marketing` notifications use `notifications.default`

Jobs retry temporary provider failures with backoff. Permanent failures and jobs that exhaust retries are marked as `dropped`.

## API

### Send Bulk Notifications

`POST /api/notifications/bulk`

The `Idempotency-Key` header is required. Repeating the same request with the same key returns the existing batch. Reusing the key with a different payload returns `409 Conflict`.

Accepted response:

```json
{
    "batch_id": 1,
    "notification_count": 2,
    "status": "queued"
}
```

Supported values:

- `channel`: `email`, `sms`
- `type`: `transactional`, `marketing`

### Subscriber Notification History

`GET /api/subscribers/{subscriber}/notifications`

Optional query parameters:

- `page`: integer, minimum `1`
- `per_page`: integer between `1` and `200`, default `50`

Notifications are returned newest first with pagination metadata.

### Postman

Import the collection from:

```text
postman/Notification-Service.postman_collection.json
```

It includes a happy-path bulk Email batch, a failure-path bulk SMS batch, idempotency conflict coverage, subscriber history, and basic response assertions. To verify idempotent replay, send the same Email batch request twice with the same `Idempotency-Key`.

## Mock Providers

Seeded subscribers include deterministic provider failure cases and fixed IDs after `make fresh`:

| Subscriber ID | Recipient | Provider behavior |
| --- | --- | --- |
| `1` | `permanent-failure@example.test` | Permanent Email failure |
| `2` | `temporary-failure@example.test` | Temporary Email failure |
| `3` | `+10000000001` | Permanent SMS failure |
| `4` | `+10000000002` | Temporary SMS failure |

The collection uses deterministic success-path subscribers for delivered flows:

| Subscriber ID | Recipient |
| --- | --- |
| `5` | `subscriber-one@example.test` |
| `6` | `subscriber-two@example.test` |

In the Postman collection:

- `{{subscriber_with_dropped_status_id}}` points to subscriber `3`
- `{{subscriber_with_delivered_status_id}}` points to subscriber `5`
- `{{second_subscriber_with_delivered_status_id}}` points to subscriber `6`

Mock providers synchronously confirm successful delivery. Therefore, successful notifications transition directly from `queued` to `delivered`. The `sent` status is reserved for providers that acknowledge submission before confirming delivery.

## Tests

Run the regular isolated test suite:

```bash
make test
```

The real-infrastructure integration test verifies:

```text
bulk API -> RabbitMQ -> queue worker -> provider -> delivered database status
```

Run the integration suite:

```bash
make test-integration
```

The Make target temporarily stops the regular queue worker so it cannot consume the integration-test job, then restores it after the test even if PHPUnit fails. The integration suite uses the `testing` PostgreSQL database, Redis, and RabbitMQ. It purges the `notifications.high` queue before and after execution.

## Delivery Guarantees

The service provides at-least-once delivery with best-effort deduplication:

- durable RabbitMQ queues
- Redis lock preventing concurrent processing of the same notification
- persisted notification status preventing completed jobs from being resent
- request idempotency backed by Redis and PostgreSQL

Exactly-once external delivery cannot be guaranteed without idempotency support from the external provider. A worker crash after provider success but before saving `delivered` may result in a repeated provider call.
