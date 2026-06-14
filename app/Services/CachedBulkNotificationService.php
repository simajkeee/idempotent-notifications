<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BulkNotification;
use App\DTO\BulkNotificationResult;
use App\Enums\BulkNotificationStatus;
use Illuminate\Support\Facades\Cache;

class CachedBulkNotificationService implements BulkNotificationSender
{
    public function __construct(
        private readonly BulkNotificationService $bulkNotificationService,
        private readonly BulkNotificationHasher $hasher,
        private readonly IdempotencyGuard $idempotencyGuard,
    ) {}

    public function send(string $idempotencyKey, BulkNotification $bulkNotification): BulkNotificationResult
    {
        $cacheKey = "idempotency:{$idempotencyKey}";
        $bodyHash = $this->hasher->hash($bulkNotification);
        /** @var ?array{
         *     body_hash: string,
         *     batch_id: int,
         *     notification_count: int,
         *     status: string
        } $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $this->idempotencyGuard->assertMatchingHash(
                $cached['body_hash'],
                $bodyHash,
            );

            return new BulkNotificationResult(
                batchId: $cached['batch_id'],
                notificationCount: $cached['notification_count'],
                status: BulkNotificationStatus::from($cached['status']),
                replayed: true,
            );
        }

        $result = $this->bulkNotificationService->send(
            $idempotencyKey,
            $bulkNotification,
        );

        Cache::put($cacheKey, [
            'body_hash' => $bodyHash,
            'batch_id' => $result->batchId,
            'notification_count' => $result->notificationCount,
            'status' => $result->status->value,
        ], now()->addDay());

        return $result;
    }
}
