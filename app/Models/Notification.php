<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Exceptions\InvalidNotificationStatusTransition;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property NotificationBatch $batch
 * @property Subscriber $recipient
 * @property NotificationStatus $status
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'recipient_id',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'recipient_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    public function isQueued(): bool
    {
        return $this->status === NotificationStatus::QUEUED;
    }

    public function markDelivered(): void
    {
        if ($this->status !== NotificationStatus::QUEUED) {
            throw new InvalidNotificationStatusTransition;
        }

        $this->status = NotificationStatus::DELIVERED;
        $this->save();
    }

    public function markDropped(): void
    {
        if ($this->status !== NotificationStatus::QUEUED) {
            throw new InvalidNotificationStatusTransition;
        }

        $this->status = NotificationStatus::DROPPED;
        $this->save();
    }

    protected function casts(): array
    {
        return ['status' => NotificationStatus::class];
    }
}
