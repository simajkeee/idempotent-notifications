<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GetSubscriberNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Subscriber;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetSubscriberNotificationHistoryController extends Controller
{
    public function __invoke(GetSubscriberNotificationRequest $request, Subscriber $subscriber): JsonResponse
    {
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 50);

        $notifications = $subscriber->notifications()
            ->with('batch')
            ->latest()
            ->paginate(
                perPage: $perPage,
                page: $page,
            );

        return NotificationResource::collection($notifications)
            ->response();
    }
}
