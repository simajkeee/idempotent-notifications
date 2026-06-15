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
vendor/bin/sail up -d
```

Generate the application key and initialize the database:

```bash
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate:fresh --seed
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

```bash
curl --request POST http://localhost/api/notifications/bulk \
    --header 'Content-Type: application/json' \
    --header 'Idempotency-Key: example-request-1' \
    --data '{
        "channel": "email",
        "type": "transactional",
        "message": "Your order has shipped.",
        "recipient_ids": [1, 2]
    }'
```

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

```bash
curl 'http://localhost/api/subscribers/1/notifications?per_page=20'
```

Notifications are returned newest first with pagination metadata.

### Postman

Import the collection from:

```text
postman/Notification-Service.postman_collection.json
```

It includes bulk notification success, idempotent replay, idempotency conflict, priority queue examples, subscriber history, and basic response assertions.

## Mock Providers

Seeded subscribers include deterministic provider failure cases:

| Recipient | Provider behavior |
| --- | --- |
| `permanent-failure@example.test` | Permanent Email failure |
| `temporary-failure@example.test` | Temporary Email failure |
| `+10000000001` | Permanent SMS failure |
| `+10000000002` | Temporary SMS failure |

Mock providers synchronously confirm successful delivery. Therefore, successful notifications transition directly from `queued` to `delivered`. The `sent` status is reserved for providers that acknowledge submission before confirming delivery.

## Tests

Run the regular isolated test suite:

```bash
vendor/bin/sail artisan test
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
