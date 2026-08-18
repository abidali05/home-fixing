<?php

namespace App\Http\Controllers;

use App\Models\Privacy;

class PrivacyController extends Controller
{
    public function index(int $role)
    {
        $content = Privacy::where('role', $role)->value('content');

        return response()->json([
            'success' => true,
            'data' => $content ?? 'Privacy details not available for this role.',
        ]);
    }
}
