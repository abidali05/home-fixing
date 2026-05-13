<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function store_fcm_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed.');
        }

        $user = auth('sanctum')->user();
        $user->fcm_token = $request->string('fcm_token')->toString();
        $user->save();

        return $this->success(null, 'Token saved successfully');
    }

    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->get()
            ->map(fn($n) => $this->formatNotification($n))
            ->values();

        return $this->success($notifications, 'Notifications fetched successfully');
    }

    public function unread(Request $request)
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->latest()
            ->get()
            ->map(fn($n) => $this->formatNotification($n))
            ->values();

        return $this->success($notifications, 'Unread notifications fetched successfully');
    }

    public function unread_count(Request $request)
    {
        $count = $request->user()->unreadNotifications()->count();
        return $this->success(['count' => $count], 'Unread count fetched successfully');
    }

    public function mark_as_read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if (!$notification) {
            return $this->notFound('Notification not found');
        }

        $notification->markAsRead();

        return $this->success($this->formatNotification($notification->fresh()), 'Notification marked as read');
    }

    public function mark_all_as_read(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return $this->success(null, 'All notifications marked as read');
    }

    private function formatNotification($notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'data' => $data['data'] ?? [],
            'read_at' => optional($notification->read_at)?->toDateTimeString(),
            'created_at' => optional($notification->created_at)?->toDateString(),
        ];
    }
}

