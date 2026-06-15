<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PermanentProviderException;
use App\Exceptions\TemporaryProviderException;
use App\Models\Notification;

class SmsProviderMock implements Provider
{
    public function send(Notification $notification): void
    {
        $subscriber = $notification->recipient;
        $phone = $subscriber->phone;
        if ($phone === null || $phone === '+10000000001') {
            throw new PermanentProviderException;
        }

        if ($phone === '+10000000002') {
            throw new TemporaryProviderException;
        }
    }
}
