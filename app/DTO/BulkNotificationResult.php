<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\BulkNotificationStatus;

final readonly class BulkNotificationResult
{
    public function __construct(
        public int $batchId,
        public int $notificationCount,
        public BulkNotificationStatus $status,
        public bool $replayed,
    ) {}
}
