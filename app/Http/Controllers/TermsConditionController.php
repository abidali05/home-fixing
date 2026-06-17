<?php

namespace App\Http\Controllers;

use App\Models\TermsCondition;
use Illuminate\Http\Request;

class TermsConditionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $role = $user->role ?? null;

        $content = TermsCondition::where('role', $role)->value('content');

        return response()->json([
            'success' => true,
            'data'    => $content ?? 'Terms & Conditions not available for this role.',
        ]);
    }
}
