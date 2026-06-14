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
}
