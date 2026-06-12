<?php

declare(strict_types=1);

namespace App\Enums;

enum UrgencyType: string
{
    case TRANSACTIONAL = 'transactional';
    case MARKETING = 'marketing';
}
