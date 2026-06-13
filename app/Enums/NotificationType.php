<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case TRANSACTIONAL = 'transactional';
    case MARKETING = 'marketing';

    public function queueName(): string
    {
        return match ($this) {
            self::TRANSACTIONAL => 'notifications.high',
            self::MARKETING => 'notifications.default',
        };
    }
}
