<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Notification;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class GetSubscriberNotificationHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriber_history_returns_only_their_notifications_latest_first(): void
    {
        $subscriber = Subscriber::factory()->create();
        $older = Notification::factory()
            ->for($subscriber, 'recipient')
            ->create(['created_at' => now()->subHour()]);

        $newer = Notification::factory()
            ->for($subscriber, 'recipient')
            ->create(['created_at' => now()]);

        $subscriberExcluded = Subscriber::factory()->create();
        $excludedNotification = Notification::factory()
            ->for($subscriberExcluded, 'recipient')
            ->create();

        $response = $this->getJson("/api/subscribers/{$subscriber->id}/notifications");
        $response->assertJsonStructure([
            'data' => [[
                'id',
                'channel',
                'type',
                'message',
                'status',
                'created_at',
                'updated_at',
            ]],
            'links',
            'meta',
        ]);
        /**
         * @var list<array{
         *     id: int
         * }> $data
         */
        $data = $response->json('data');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertCount(2, $data);

        $notificationsIds = collect($data)->pluck('id')->all();
        self::assertNotContains(
            $excludedNotification->id,
            $notificationsIds
        );
        self::assertSame($newer->id, $notificationsIds[0]);
        self::assertSame($older->id, $notificationsIds[1]);
    }
}
