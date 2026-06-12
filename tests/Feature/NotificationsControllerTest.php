<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class NotificationsControllerTest extends TestCase
{
    public function test_bulk_request_with_missing_fields_is_rejected(): void
    {
        $response = $this->postJson('/api/notifications/bulk');

        $this->assertNotSame(404, $response->getStatusCode());

        $response->assertJsonValidationErrors(['channel', 'type', 'message', 'recipient_ids']);
    }
}
