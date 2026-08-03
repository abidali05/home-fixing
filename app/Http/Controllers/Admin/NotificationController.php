<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Jobs\SendFcmTokenNotificationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function create()
    {
        $usersList = User::query()
            ->where('status', 'active')
            ->select('id', 'name', 'email', 'phone', 'role', 'has_roles')
            ->orderBy('name')
            ->get();

        return view('admin.notifications.create', compact('usersList'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'target_mode' => 'required|in:group,specific',
            'target_audience' => 'required_if:target_mode,group|nullable|in:all,0,1,2',
            'user_ids' => 'required_if:target_mode,specific|nullable|array',
            'user_ids.*' => 'exists:users,id',
            'event_type' => 'required|string|in:system_alert,promotional,event_update,account_notice,custom_event',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'custom_payload' => 'nullable|string',
        ]);

        $mode = $request->target_mode;
        $title = $request->title;
        $body = $request->body;
        $eventType = $request->event_type;
        $customPayload = $request->input('custom_payload', '');

        // Query target users
        $query = User::query()->where('status', 'active');

        if ($mode === 'specific') {
            $query->whereIn('id', $request->user_ids ?? []);
        } else {
            $target = $request->target_audience;
            if ($target === '0') {
                $query->where(function ($b) {
                    $b->where('role', '0')
                        ->orWhere('role', 0)
                        ->orWhere('has_roles', '0')
                        ->orWhere('has_roles', 0)
                        ->orWhereRaw("FIND_IN_SET('0', has_roles)");
                });
            } elseif ($target === '1') {
                $query->where(function ($b) {
                    $b->where('role', '1')
                        ->orWhere('role', 1)
                        ->orWhere('has_roles', '1')
                        ->orWhere('has_roles', 1)
                        ->orWhere('has_roles', 'like', '%1%')
                        ->orWhereRaw("FIND_IN_SET('1', has_roles)")
                        ->orWhereHas('providerProfile');
                });
            } elseif ($target === '2') {
                $query->where(function ($b) {
                    $b->where('role', '2')
                        ->orWhere('role', 2)
                        ->orWhere('has_roles', '2')
                        ->orWhere('has_roles', 2)
                        ->orWhere('has_roles', 'like', '%2%')
                        ->orWhereRaw("FIND_IN_SET('2', has_roles)")
                        ->orWhereHas('marketplaceProfile');
                });
            }
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return back()->with('error', 'No active users found for the selected audience.');
        }

        DB::beginTransaction();
        try {
            $tokens = [];
            $insertData = [];
            $now = now();

            foreach ($users as $user) {
                $insertData[] = [
                    'user_id' => $user->id,
                    'title' => $title,
                    'body' => $body,
                    'is_read' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (!empty($user->fcm_token)) {
                    $tokens[] = $user->fcm_token;
                }
            }

            foreach (array_chunk($insertData, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }

            DB::commit();

            if (!empty($tokens)) {
                dispatch(new SendFcmTokenNotificationJob($tokens, [
                    'title' => $title,
                    'body' => $body,
                    'data' => [
                        'type' => $eventType,
                        'event_type' => $eventType,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'custom_payload' => $customPayload,
                        'sent_at' => $now->toDateTimeString(),
                    ]
                ]));
            }

            return redirect()->route('admin.notifications.create')->with('success', 'Push Notification broadcasted successfully to ' . count($users) . ' user(s).');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk notification broadcasting failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to broadcast notification. Please try again.');
        }
    }

    public function sendDirectNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'event_type' => 'nullable|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $title = $request->title;
        $body = $request->body;
        $eventType = $request->input('event_type', 'system_alert');

        DB::beginTransaction();
        try {
            DB::table('notifications')->insert([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            if (!empty($user->fcm_token)) {
                dispatch(new SendFcmTokenNotificationJob([$user->fcm_token], [
                    'title' => $title,
                    'body' => $body,
                    'data' => [
                        'type' => $eventType,
                        'event_type' => $eventType,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'user_id' => (string) $user->id,
                        'sent_at' => now()->toDateTimeString(),
                    ]
                ]));
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Push notification sent to ' . $user->name . ' successfully.'
                ]);
            }

            return back()->with('success', 'Push notification sent to ' . $user->name . ' successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Direct notification failed: ' . $e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification.'
                ], 500);
            }

            return back()->with('error', 'Failed to send notification.');
        }
    }
}
