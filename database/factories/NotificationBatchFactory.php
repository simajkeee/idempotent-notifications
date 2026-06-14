<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\NotificationType;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationBatch>
 */
class NotificationBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => fake()->uuid(),
            'request_body_hash' => hash('sha256', fake()->uuid()),
            'channel' => Channel::EMAIL,
            'type' => NotificationType::TRANSACTIONAL,
            'message' => fake()->sentence(),
            'recipients_count' => 1,
        ];
    }
}
