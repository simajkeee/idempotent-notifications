<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_request_with_missing_fields_is_rejected(): void
    {
        $response = $this->postJson('/api/notifications/bulk');

        $this->assertNotSame(404, $response->getStatusCode());

        $response->assertOnlyJsonValidationErrors(['idempotency_key', 'channel', 'type', 'message', 'recipient_ids']);
    }
}
