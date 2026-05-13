<?php

namespace App\Http\Controllers;

use App\Models\Privacy;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $role = $user->role ?? null;

        $content = Privacy::where('role', $role)->value('content');

        return response()->json([
            'success' => true,
            'data' => $content ?? 'Privacy details not available for this role.',
        ]);
    }
}
