<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Role;
use App\Models\AdminUsers;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin\RolePermissions;

class SystemUserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = AdminUsers::orderBy('id', 'desc')->where('id', '!=', '1')->get();

            $user = auth()->user();
            // Get user's role permissions
            $rolePermissions = [];
            if ($user) {
                $rolePermissions = RolePermissions::where('role_id', $user->role)
                    ->pluck('permission_id')
                    ->toArray();
            }

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('status', function ($row) {
                    return $row->status == 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                })

                ->addColumn('action', function ($row) use ($rolePermissions) {
                    $editUrl = route('system_users.edit', $row->id);
                    $actionBtns = '';
               
                    if ( in_array(12, $rolePermissions)) {
                        $actionBtns .= '<a href="' . $editUrl . '" class="text-primary me-3"><i class="bi bi-pencil-square"></i></a>';
                    }
                    if (in_array(13, $rolePermissions)) {
                        $actionBtns .= '<a href="javascript:void(0);" class="text-danger deleteRoleBtn" data-id="' . $row->id . '"><i class="bi bi-trash-fill"></i></a>';
                    }
                    return $actionBtns;
                })

                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('admin.system_users.index');
    }

    public function create(Request $request)
    {
        $roles = Role::where('id', '!=', '1')->get();
        return view('admin.system_users.create', compact('roles'));
    }

    public function checkEmailAvailability(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:admin_users,email'
        ]);
        return response()->json(['status' => 200, 'message' => 'Email is available.'], 200);
    }

    public function checkPhoneAvailability(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:admin_users,phone'],
        ]);

        return response()->json(['status' => 200, 'message' => 'Phone number is available.'], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email',
            'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:admin_users,phone'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
            ],
            'role' => 'required|exists:roles,id',
        ]);

        try {
            DB::beginTransaction();

            $user = AdminUsers::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'address' => $request->address,
                'status' => $request->status,
            ]);

            $password = $request->password;
            $loginUrl = route('login');

            $body = view('emails.login_credentials', compact('user', 'password', 'loginUrl'))->render();

            sendMail($user->name, $user->email, 'HomeFixing', 'Login Credentials', $body);

            DB::commit();

            return redirect()->route('system_users.index')->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User creation failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to create user. Please try again.')->withInput();
        }
    }

    public function edit($id)
    {
        $user = AdminUsers::findOrFail($id);
        $roles = Role::where('id', '!=', '1')->get();

        return view('admin.system_users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, $id)
    {
        $user = AdminUsers::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email,' . $id,
            'phone' => [
                'required',
                'regex:/^\+9665[0-9]{8}$/',
                'unique:admin_users,phone,' . $id,
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
                'confirmed'
            ],
            'role' => 'required|exists:roles,id',
            'address' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        try {
            DB::beginTransaction();

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role' => $request->role,
                'address' => $request->address,
                'status' => $request->status,
                'password' => $request->filled('password') ? Hash::make($request->password) : $user->password,
            ]);

            if ($request->filled('password')) {
                $password = $request->password;
                $loginUrl = route('login');

                $body = view('emails.login_credentials', compact('user', 'password', 'loginUrl'))->render();

                sendMail($user->name, $user->email, 'HomeFixing', 'Login Credentials', $body);
            }

            DB::commit();

            return redirect()->route('system_users.index')->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User update failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to update user. Please try again.')->withInput();
        }
    }

    public function destroy($id)
    {
        $user = AdminUsers::findOrFail($id);
        $user->delete();

        return response()->json([
            'status' => 200,
            'message' => 'User deleted successfully.'
        ]);
    }
}
