<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\PermanentProviderException;
use App\Models\Notification;
use App\Services\ProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Tries(4)]
#[Backoff([65, 300, 1800])]
class ProcessNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $timeout = 30;

    public function __construct(public readonly int $notificationId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("notification:{$this->notificationId}"))
                ->releaseAfter(65)
                ->expireAfter(60),
        ];
    }

    public function handle(ProviderResolver $resolver): void
    {
        $notification = Notification::with(['batch', 'recipient'])
            ->findOrFail($this->notificationId);

        if (! $notification->isQueued()) {
            return;
        }

        $provider = $resolver->resolve($notification->batch->channel);
        try {
            $provider->send($notification);
        } catch (PermanentProviderException) {
            $notification->markDropped();

            return;
        }

        $notification->markDelivered();
    }

    public function failed(?\Throwable $exception): void
    {
        Notification::find($this->notificationId)?->markDropped();
    }
}
