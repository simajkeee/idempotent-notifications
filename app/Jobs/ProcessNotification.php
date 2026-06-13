<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public readonly int $notificationId) {}

    public function handle(): void
    {
        $notification = Notification::with(['batch', 'recipient'])
            ->findOrFail($this->notificationId);

        if (! $notification->isQueued()) {
            return;
        }

        // call later
    }
}
