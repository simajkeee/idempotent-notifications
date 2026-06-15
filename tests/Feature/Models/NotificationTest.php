<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\NotificationStatus;
use App\Exceptions\InvalidNotificationStatusTransition;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_notification_can_be_marked_as_delivered(): void
    {
        $notification = Notification::factory()->createOne();

        $notification->markDelivered();

        self::assertSame(
            NotificationStatus::DELIVERED,
            $notification->fresh()->status,
        );
    }

    public function test_queued_notification_can_be_marked_as_dropped(): void
    {
        $notification = Notification::factory()->createOne();

        $notification->markDropped();

        self::assertSame(
            NotificationStatus::DROPPED,
            $notification->fresh()->status,
        );
    }

    #[DataProvider('invalidTransitionsProvider')]
    public function test_completed_notification_rejects_status_transition(
        NotificationStatus $currentStatus,
        string $transition,
    ): void {
        $notification = Notification::factory()->createOne();
        $notification->status = $currentStatus;
        $notification->save();

        $this->expectException(InvalidNotificationStatusTransition::class);

        $notification->{$transition}();
    }

    /**
     * @return iterable<string, array{NotificationStatus, string}>
     */
    public static function invalidTransitionsProvider(): iterable
    {
        yield 'delivered to delivered' => [
            NotificationStatus::DELIVERED,
            'markDelivered',
        ];

        yield 'delivered to dropped' => [
            NotificationStatus::DELIVERED,
            'markDropped',
        ];

        yield 'dropped to delivered' => [
            NotificationStatus::DROPPED,
            'markDelivered',
        ];

        yield 'dropped to dropped' => [
            NotificationStatus::DROPPED,
            'markDropped',
        ];
    }
}
