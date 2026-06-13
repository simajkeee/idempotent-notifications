<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\Channel;
use App\Enums\NotificationType;

final readonly class BulkNotification
{
    public function __construct(
        public Channel $channel,
        public NotificationType $type,
        public string $message,
        public array $recipientIds,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            channel: Channel::from($data['channel']),
            type: NotificationType::from($data['type']),
            message: $data['message'],
            recipientIds: $data['recipient_ids']
        );
    }

    public function recipientsCount(): int
    {
        return count($this->recipientIds);
    }

    public function canonicalPayload(): array
    {
        $recipientsIds = $this->recipientIds;
        sort($recipientsIds);

        return [
            'channel' => $this->channel->value,
            'message' => $this->message,
            'recipient_ids' => $recipientsIds,
            'type' => $this->type->value,
        ];
    }
}
