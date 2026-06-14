<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BulkNotification;

final class BulkNotificationHasher
{
    public function hash(BulkNotification $bulkNotification): string
    {
        return hash(
            'sha256',
            json_encode(
                $bulkNotification->canonicalPayload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }
}
