<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotification;
use App\Models\Notification;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Queue;
use Tests\TestCase;

class NotificationsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_request_with_missing_fields_is_rejected(): void
    {
        $response = $this->postJson('/api/notifications/bulk');
        $response->assertUnprocessable();
        $response->assertOnlyJsonValidationErrors(['idempotency_key', 'channel', 'type', 'message', 'recipient_ids']);
    }

    public function test_bulk_request_creates_queued_notifications(): void
    {
        Queue::fake();
        $subscribers = Subscriber::factory()->count(3)->create();

        $response = $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'type' => 'transactional',
            'message' => 'Now you have access to...',
            'recipient_ids' => $subscribers->pluck('id')->all(),
        ], ['Idempotency-Key' => 'key-1']);

        $response->assertAccepted()
            ->assertJson([
                'notification_count' => 3,
                'status' => 'queued',
            ]);

        $batchId = $response->json('batch_id');

        $this->assertDatabaseCount('notification_batches', 1);
        $this->assertDatabaseHas('notification_batches', [
            'id' => $batchId,
            'idempotency_key' => 'key-1',
            'channel' => 'email',
            'type' => 'transactional',
            'message' => 'Now you have access to...',
            'recipients_count' => 3,
        ]);

        foreach ($subscribers as $subscriber) {
            $this->assertDatabaseHas('notifications', [
                'batch_id' => $batchId,
                'recipient_id' => $subscriber->id,
                'status' => NotificationStatus::QUEUED->value,
            ]);
        }

        Queue::assertPushed(ProcessNotification::class, 3);

        foreach (Notification::all() as $notification) {
            Queue::assertPushed(
                ProcessNotification::class,
                fn (ProcessNotification $job) => $job->notificationId === $notification->id
                    && $job->queue === 'notifications.high',
            );
        }
    }

    public function test_duplicate_request_returns_cached_response_without_creating_duplicates(): void
    {
        Queue::fake();
        $subscribers = Subscriber::factory()->count(3)->create();
        $payload = [
            'channel' => 'email',
            'type' => 'transactional',
            'message' => 'Now you have access to...',
            'recipient_ids' => $subscribers->pluck('id')->all(),
        ];
        $headers = ['Idempotency-Key' => 'duplicate-key'];

        $firstResponse = $this->postJson('/api/notifications/bulk', $payload, $headers);
        $secondResponse = $this->postJson('/api/notifications/bulk', $payload, $headers);

        $firstResponse->assertAccepted();
        $secondResponse->assertOk()
            ->assertExactJson($firstResponse->json());

        $this->assertDatabaseCount('notification_batches', 1);
        $this->assertDatabaseCount('notifications', 3);
        Queue::assertPushed(ProcessNotification::class, 3);
    }

    public function test_same_idempotency_key_with_different_request_returns_conflict(): void
    {
        Queue::fake();
        $subscribers = Subscriber::factory()->count(3)->create();
        $payload = [
            'channel' => 'email',
            'type' => 'transactional',
            'message' => 'Original message',
            'recipient_ids' => $subscribers->pluck('id')->all(),
        ];
        $headers = ['Idempotency-Key' => 'conflicting-key'];

        $firstResponse = $this->postJson('/api/notifications/bulk', $payload, $headers);
        $conflictingResponse = $this->postJson('/api/notifications/bulk', [
            ...$payload,
            'message' => 'Different message',
        ], $headers);

        $firstResponse->assertAccepted();
        $conflictingResponse->assertConflict()
            ->assertExactJson([
                'error' => 'idempotency_key_conflict',
            ]);

        $this->assertDatabaseCount('notification_batches', 1);
        $this->assertDatabaseCount('notifications', 3);
        Queue::assertPushed(ProcessNotification::class, 3);
    }
}
