<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BulkNotification;
use App\DTO\BulkNotificationResult;
use App\Enums\BulkNotificationStatus;
use Illuminate\Support\Facades\Cache;

readonly class CachedBulkNotificationService implements BulkNotificationSender
{
    public function __construct(
        private BulkNotificationService $bulkNotificationService,
        private BulkNotificationHasher $hasher,
        private IdempotencyGuard $idempotencyGuard,
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
        $cached = Cache::store('redis')->get($cacheKey);
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

        Cache::store('redis')->put($cacheKey, [
            'body_hash' => $bodyHash,
            'batch_id' => $result->batchId,
            'notification_count' => $result->notificationCount,
            'status' => $result->status->value,
        ], now()->addDay());

        return $result;
    }
}
