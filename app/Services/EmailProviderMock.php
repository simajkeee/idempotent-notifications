<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PermanentProviderException;
use App\Exceptions\TemporaryProviderException;
use App\Models\Notification;

class EmailProviderMock implements Provider
{
    public function send(Notification $notification): void
    {
        $subscriber = $notification->recipient;
        $email = $subscriber->email;
        if ($email === null || $email === 'permanent-failure@example.test') {
            throw new PermanentProviderException;
        }

        if ($email === 'temporary-failure@example.test') {
            throw new TemporaryProviderException;
        }
    }
}
