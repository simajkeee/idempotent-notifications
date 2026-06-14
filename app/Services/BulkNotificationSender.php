<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BulkNotification;
use App\DTO\BulkNotificationResult;

interface BulkNotificationSender
{
    public function send(string $idempotencyKey, BulkNotification $bulkNotification): BulkNotificationResult;
}
