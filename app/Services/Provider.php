<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;

interface Provider
{
    public function send(Notification $notification): void;
}
