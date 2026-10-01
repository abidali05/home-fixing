<?php

namespace App\Http\Controllers\Admin;

use App\Models\AccountActiveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\DataTables\DataTables;

class AccountActiveRequestController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = AccountActiveRequest::with('user')->orderBy('id', 'desc')->get();
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('user_name', function ($row) {
                    return $row->user ? $row->user->name : 'N/A';
                })
                ->addColumn('user_email', function ($row) {
                    return $row->user ? $row->user->email : 'N/A';
                })
                ->addColumn('user_phone', function ($row) {
                    return $row->user ? $row->user->phone : 'N/A';
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at ? $row->created_at->format('Y-m-d H:i') : '';
                })
                ->addColumn('action', function ($row) {
                    return '<button class="btn btn-sm btn-success activateUserBtn" data-id="' . $row->id . '">Activate Account</button>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.account_active_requests.index');
    }

    public function activate($id)
    {
        try {
            $activeRequest = AccountActiveRequest::findOrFail($id);
            $user = User::findOrFail($activeRequest->user_id);

            // Activate the user account depending on their current role
            $role = (int) $user->role;
            if ($role === 1) {
                $user->provider_status = 'active';
            } elseif ($role === 2) {
                $user->marketplace_status = 'active';
            } else {
                $user->status = 'active';
            }
            $user->save();

            // Remove the related request from the Account Active Request section
            $activeRequest->delete();

            return response()->json([
                'status' => 200,
                'message' => 'User account activated successfully and request removed.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to activate account.'
            ], 500);
        }
    }
}
