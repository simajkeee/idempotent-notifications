<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendBulkNotificationRequest;
use Illuminate\Http\JsonResponse;

class SendBulkNotificationsController extends Controller
{
    public function __invoke(SendBulkNotificationRequest $request): JsonResponse
    {
        return response()->json(['test' => 'test'], 202);
    }
}
