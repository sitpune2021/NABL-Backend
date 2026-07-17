<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TaskNotification;

class TaskNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $notifications = TaskNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'status' => $notification->status,
                    'read_at' => $notification->read_at?->toDateTimeString(),
                    'created_at' => $notification->created_at->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $notifications,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = auth()->user();

        $count = TaskNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'status' => true,
            'data' => ['unread' => $count],
        ]);
    }

    public function markAsRead(string $id)
    {
        $user = auth()->user();

        $notification = TaskNotification::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $user = auth()->user();

        TaskNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read',
        ]);
    }
}
