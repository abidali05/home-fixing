<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderCommissionPaid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('sanctum')->user() ?? auth()->user();

        if ($user && ((int) $user->role === 1 || (string) $user->role === 'provider')) {
            $threshold = (float) ($user->max_due_threshold ?? 25.00);
            $due = (float) ($user->payment_due ?? 0.0);

            if ($due >= $threshold || (int) ($user->is_due_blocked ?? 0) === 1) {
                return response()->json([
                    'status' => false,
                    'error_code' => 'COMMISSION_THRESHOLD_EXCEEDED',
                    'message' => 'Your outstanding Azhl commission (SAR ' . number_format($due, 2) . ') exceeds the threshold limit of SAR ' . number_format($threshold, 2) . '. Please clear your dues in the app to continue.',
                    'payment_due' => $due,
                    'threshold' => $threshold,
                ], 403);
            }
        }

        return $next($request);
    }
}
