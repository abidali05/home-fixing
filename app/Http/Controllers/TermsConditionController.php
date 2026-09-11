<?php

namespace App\Http\Controllers;

use App\Models\TermsCondition;

class TermsConditionController extends Controller
{
    public function index(int $role)
    {
        $content = TermsCondition::where('role', $role)->value('content');

        return response()->json([
            'success' => true,
            'data'    => $content ?? 'Terms & Conditions not available for this role.',
        ]);
    }
}
