<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BulkNotification;
use App\DTO\BulkNotificationResult;
use App\Enums\BulkNotificationStatus;
use App\Exceptions\IdempotencyKeyException;
use App\Jobs\ProcessNotification;
use App\Models\NotificationBatch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class BulkNotificationService implements BulkNotificationSender
{
    public function __construct(
        private readonly BulkNotificationHasher $hasher,
        private readonly IdempotencyGuard $idempotencyGuard,
    ) {}

    /**
     * @throws IdempotencyKeyException
     * @throws \JsonException
     */
    public function send(string $idempotencyKey, BulkNotification $bulkNotification): BulkNotificationResult
    {
        $bodyHash = $this->hasher->hash($bulkNotification);
        $result = $this->getIdempotencyResult($idempotencyKey, $bodyHash);
        if ($result !== null) {
            return $result;
        }

        try {
            $batch = $this->createBatchWithNotifications($idempotencyKey, $bodyHash, $bulkNotification);
        } catch (UniqueConstraintViolationException $e) {
            $result = $this->getIdempotencyResult($idempotencyKey, $bodyHash);
            if ($result !== null) {
                return $result;
            }

            throw $e;
        }
        $this->dispatchNotifications($batch->notifications, $bulkNotification->type->queueName());

        return new BulkNotificationResult(
            batchId: $batch->id,
            notificationCount: $batch->recipients_count,
            status: BulkNotificationStatus::QUEUED,
            replayed: false
        );
    }

    private function getIdempotencyResult(string $idempotencyKey, string $bodyHash): ?BulkNotificationResult
    {
        $existingBatch = NotificationBatch::where('idempotency_key', $idempotencyKey)->first();
        if ($existingBatch !== null) {
            $this->idempotencyGuard->assertMatchingHash(
                $existingBatch->request_body_hash,
                $bodyHash,
            );

            return new BulkNotificationResult(
                batchId: $existingBatch->id,
                notificationCount: $existingBatch->recipients_count,
                status: BulkNotificationStatus::QUEUED,
                replayed: true
            );
        }

        return null;
    }

    private function createBatchWithNotifications(string $idempotencyKey, string $bodyHash, BulkNotification $bulkNotification): NotificationBatch
    {
        return DB::transaction(function () use ($idempotencyKey, $bodyHash, $bulkNotification): NotificationBatch {
            $batch = NotificationBatch::create([
                'idempotency_key' => $idempotencyKey,
                'request_body_hash' => $bodyHash,
                'channel' => $bulkNotification->channel->value,
                'type' => $bulkNotification->type->value,
                'message' => $bulkNotification->message,
                'recipients_count' => $bulkNotification->recipientsCount(),
            ]);

            foreach ($bulkNotification->recipientIds as $recipientId) {
                $batch->notifications()->create([
                    'recipient_id' => $recipientId,
                ]);
            }

            return $batch;
        });
    }

    private function dispatchNotifications(iterable $notifications, string $queueName): void
    {
        foreach ($notifications as $notification) {
            ProcessNotification::dispatch($notification->id)
                ->onQueue($queueName);
        }
    }
}
