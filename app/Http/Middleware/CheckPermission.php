<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\RolePermissions;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permissionId): Response
    {
        $user = Auth::guard('admin')->user();
        if ($user) {
            if ($user->is_company) {
                return $next($request);
            }
            $rolePermissions = RolePermissions::where('role_id', $user->role)
                ->pluck('permission_id')
                ->toArray();
            if (in_array($permissionId, $rolePermissions)) {
                return $next($request);
            }
        }
        return redirect()->route('unauthorized')->with('error', 'You do not have permission to access this page.');
    }
}
