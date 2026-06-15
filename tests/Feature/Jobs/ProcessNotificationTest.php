<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\PermanentProviderException;
use App\Exceptions\TemporaryProviderException;
use App\Jobs\ProcessNotification;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\Subscriber;
use App\Services\EmailProviderMock;
use App\Services\ProviderResolver;
use App\Services\SmsProviderMock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_email_notification_is_sent_and_marked_as_delivered(): void
    {
        $batch = NotificationBatch::factory()->create();
        $subscriber = Subscriber::factory()->create();
        $notification = Notification::factory()
            ->for($batch, 'batch')
            ->for($subscriber, 'recipient')
            ->create();

        $emailProvider = Mockery::mock(EmailProviderMock::class);
        $emailProvider->expects('send')->once();

        $smsProvider = Mockery::mock(SmsProviderMock::class);
        $smsProvider->shouldNotReceive('send');

        $resolver = new ProviderResolver($emailProvider, $smsProvider);

        $job = new ProcessNotification($notification->id);
        $job->handle($resolver);

        self::assertSame(
            NotificationStatus::DELIVERED,
            $notification->fresh()->status,
        );
    }

    public function test_non_queued_notification_is_ignored(): void
    {
        $notification = Notification::factory()->create([
            'status' => NotificationStatus::DELIVERED,
        ]);

        $emailProvider = Mockery::mock(EmailProviderMock::class);
        $emailProvider->shouldNotReceive('send');

        $smsProvider = Mockery::mock(SmsProviderMock::class);
        $smsProvider->shouldNotReceive('send');

        $job = new ProcessNotification($notification->id);
        $job->handle(new ProviderResolver($emailProvider, $smsProvider));

        self::assertSame(
            NotificationStatus::DELIVERED,
            $notification->fresh()->status,
        );
    }

    public function test_temporary_provider_failure_is_rethrown_and_notification_remains_queued(): void
    {
        $notification = Notification::factory()->createOne();

        $emailProvider = Mockery::mock(EmailProviderMock::class);
        $emailProvider->expects('send')
            ->once()
            ->with(Mockery::on(
                fn (Notification $argument): bool => $argument->is($notification)
            ))
            ->andThrow(new TemporaryProviderException);

        $smsProvider = Mockery::mock(SmsProviderMock::class);
        $smsProvider->shouldNotReceive('send');

        $job = new ProcessNotification($notification->id);

        try {
            $job->handle(new ProviderResolver($emailProvider, $smsProvider));
            self::fail("TemporaryProviderException wasn't thrown");
        } catch (TemporaryProviderException) {
            self::assertSame(
                NotificationStatus::QUEUED,
                $notification->fresh()->status,
            );
        }
    }

    public function test_permanent_provider_failure_marks_notification_as_dropped(): void
    {
        $notification = Notification::factory()->createOne();

        $emailProvider = Mockery::mock(EmailProviderMock::class);
        $emailProvider->expects('send')
            ->once()
            ->with(Mockery::on(
                fn (Notification $argument): bool => $argument->is($notification)
            ))
            ->andThrow(new PermanentProviderException);

        $smsProvider = Mockery::mock(SmsProviderMock::class);
        $smsProvider->shouldNotReceive('send');

        $job = new ProcessNotification($notification->id);

        $job->handle(new ProviderResolver($emailProvider, $smsProvider));

        self::assertSame(
            NotificationStatus::DROPPED,
            $notification->fresh()->status,
        );
    }

    public function test_failed_marks_notification_as_dropped(): void
    {
        $notification = Notification::factory()->createOne();

        $job = new ProcessNotification($notification->id);
        $job->failed(new TemporaryProviderException);

        self::assertSame(
            NotificationStatus::DROPPED,
            $notification->fresh()->status,
        );
    }
}
