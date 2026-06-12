<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\NotificationType;
use Database\Factories\NotificationBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationBatch extends Model
{
    /** @use HasFactory<NotificationBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'request_body_hash',
        'channel',
        'type',
        'message',
        'recipients_count',
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }

    protected function casts(): array
    {
        return ['channel' => Channel::class, 'type' => NotificationType::class];
    }
}
