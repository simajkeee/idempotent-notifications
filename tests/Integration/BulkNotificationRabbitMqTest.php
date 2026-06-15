<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class BulkNotificationRabbitMqTest extends TestCase
{
    use DatabaseMigrations;

    private const string QUEUE = 'notifications.high';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'redis',
            'queue.default' => 'rabbitmq',
        ]);

        $this->purgeQueue();
    }

    protected function tearDown(): void
    {
        try {
            $this->purgeQueue();
        } finally {
            parent::tearDown();
        }
    }

    public function test_bulk_api_job_is_processed_by_rabbitmq_worker_and_marked_as_delivered(): void
    {
        $subscriber = Subscriber::factory()->createOne([
            'email' => 'successful-delivery@example.test',
            'phone' => null,
        ]);

        $response = $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'type' => 'transactional',
            'message' => 'Your order has shipped.',
            'recipient_ids' => [$subscriber->id],
        ], [
            'Idempotency-Key' => 'integration-'.Str::uuid(),
        ]);

        $response->assertAccepted();

        $notification = Notification::query()
            ->where('batch_id', $response->json('batch_id'))
            ->where('recipient_id', $subscriber->id)
            ->sole();

        self::assertSame(NotificationStatus::QUEUED, $notification->status);

        $worker = new Process([
            PHP_BINARY,
            'artisan',
            'queue:work',
            'rabbitmq',
            '--queue='.self::QUEUE,
            '--once',
            '--tries=4',
            '--timeout=30',
        ], base_path(), [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => 'rabbitmq',
        ], timeout: 45);

        $worker->mustRun();

        self::assertSame(
            NotificationStatus::DELIVERED,
            $notification->fresh()->status,
        );
    }

    private function purgeQueue(): void
    {
        /** @var RabbitMQQueue $queue */
        $queue = Queue::connection('rabbitmq');

        $queue->declareQueue(self::QUEUE);
        $queue->purge(self::QUEUE);
    }
}
