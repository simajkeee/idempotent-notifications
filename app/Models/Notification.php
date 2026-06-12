<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatus;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'recipient_id',
        'status',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'recipient_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    protected function casts(): array
    {
        return ['status' => NotificationStatus::class];
    }
}
