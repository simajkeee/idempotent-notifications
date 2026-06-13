<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyKeyException;
use App\Http\Requests\SendBulkNotificationRequest;
use App\Services\BulkNotificationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SendBulkNotificationsController extends Controller
{
    public function __construct(private BulkNotificationService $bulkNotificationService) {}

    public function __invoke(SendBulkNotificationRequest $request): JsonResponse
    {
        try {
            $result = $this->bulkNotificationService
                ->send(
                    idempotencyKey: (string) $request->safe()->string('idempotency_key'),
                    bulkNotification: $request->toDto()
                );
        } catch (IdempotencyKeyException $e) {
            // log
            return response()->json([
                'error' => 'idempotency_key_conflict',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'batch_id' => $result->batchId,
            'notification_count' => $result->notificationCount,
            'status' => $result->status->value,
        ], $result->replayed ? 200 : 202);
    }
}
