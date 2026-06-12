<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case DROPPED = 'dropped';
}
