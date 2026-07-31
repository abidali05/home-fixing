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
        return view('admin.notifications.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'target_audience' => 'required|in:all,0,1,2',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $target = $request->target_audience;
        $title = $request->title;
        $body = $request->body;

        // Query target users
        $query = User::query()->where('status', 'active');
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
                // Prepare database notification entry
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

            // Insert into database in chunks to prevent database query limits
            foreach (array_chunk($insertData, 500) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }

            DB::commit();

            // Dispatch FCM notification if tokens are present
            if (!empty($tokens)) {
                dispatch(new SendFcmTokenNotificationJob($tokens, [
                    'title' => $title,
                    'body' => $body,
                    'data' => [
                        'type' => 'system_alert',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]
                ]));
            }

            return redirect()->route('admin.notifications.create')->with('success', 'Notification broadcasted successfully to ' . count($users) . ' user(s).');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk notification broadcasting failed', [
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Failed to broadcast notification. Please try again.');
        }
    }
}
