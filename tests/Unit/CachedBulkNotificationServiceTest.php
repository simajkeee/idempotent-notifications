<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\BulkNotification;
use App\DTO\BulkNotificationResult;
use App\Enums\BulkNotificationStatus;
use App\Enums\Channel;
use App\Enums\NotificationType;
use App\Services\BulkNotificationHasher;
use App\Services\BulkNotificationService;
use App\Services\CachedBulkNotificationService;
use App\Services\IdempotencyGuard;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class CachedBulkNotificationServiceTest extends TestCase
{
    public function test_first_hit_calls_inner_service_and_stores_result_in_cache(): void
    {
        $idempotencyKey = 'idempotency-key';
        $bulkNotification = new BulkNotification(
            channel: Channel::EMAIL,
            type: NotificationType::TRANSACTIONAL,
            message: 'message',
            recipientIds: [1, 2, 3],
        );
        $bulkNotificationResult = new BulkNotificationResult(
            batchId: 1,
            notificationCount: 3,
            status: BulkNotificationStatus::QUEUED,
            replayed: false,
        );
        $bulkNotificationService = Mockery::mock(BulkNotificationService::class);
        $bulkNotificationService->expects('send')
            ->once()
            ->with($idempotencyKey, $bulkNotification)
            ->andReturn($bulkNotificationResult);

        $hasher = Mockery::mock(BulkNotificationHasher::class);
        $hasher->expects('hash')
            ->once()
            ->andReturn('hash');

        $cachedBulkNotification = new CachedBulkNotificationService(
            $bulkNotificationService,
            $hasher,
            Mockery::mock(IdempotencyGuard::class)
        );

        $result = $cachedBulkNotification->send($idempotencyKey, $bulkNotification);

        self::assertSame($bulkNotificationResult, $result);
        self::assertSame([
            'body_hash' => 'hash',
            'batch_id' => 1,
            'notification_count' => 3,
            'status' => BulkNotificationStatus::QUEUED->value,
        ], Cache::get("idempotency:{$idempotencyKey}"));
    }

    public function test_double_hit_returns_cached_version(): void
    {
        $idempotencyKey = 'idempotency-key';
        $bulkNotification = new BulkNotification(
            channel: Channel::EMAIL,
            type: NotificationType::TRANSACTIONAL,
            message: 'message',
            recipientIds: [1, 2, 3],
        );
        $bulkNotificationResult = new BulkNotificationResult(
            batchId: 1,
            notificationCount: 3,
            status: BulkNotificationStatus::QUEUED,
            replayed: false,
        );
        $bulkNotificationService = Mockery::mock(BulkNotificationService::class);
        $bulkNotificationService->expects('send')
            ->once()
            ->with($idempotencyKey, $bulkNotification)
            ->andReturn($bulkNotificationResult);

        $hasher = Mockery::mock(BulkNotificationHasher::class);
        $hasher->expects('hash')
            ->twice()
            ->andReturn('hash');

        $idempotencyGuard = Mockery::mock(IdempotencyGuard::class);
        $idempotencyGuard->expects('assertMatchingHash')
            ->once()
            ->with('hash', 'hash');

        $cachedBulkNotification = new CachedBulkNotificationService(
            $bulkNotificationService,
            $hasher,
            $idempotencyGuard,
        );

        $resultNotCached = $cachedBulkNotification->send($idempotencyKey, $bulkNotification);
        $resultCached = $cachedBulkNotification->send($idempotencyKey, $bulkNotification);

        self::assertSame($bulkNotificationResult, $resultNotCached);

        self::assertSame($bulkNotificationResult->batchId, $resultCached->batchId);
        self::assertSame($bulkNotificationResult->notificationCount, $resultCached->notificationCount);
        self::assertSame($bulkNotificationResult->status, $resultCached->status);
        self::assertTrue($resultCached->replayed);
    }
}
