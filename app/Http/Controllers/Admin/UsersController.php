<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\CityModel;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin\RolePermissions;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = User::orderBy('id', 'desc')->where('role', '0')->get();
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

                ->addColumn('profile_image', function ($row) {
                    $imageUrl = $row->profile_image
                        ? asset('uploads/profile_images/' . $row->profile_image)
                        : asset('assets/img/default.jpg');

                    return '<img src="' . $imageUrl . '" alt="Profile" width="40" height="40" class="rounded-circle">';
                })

                ->editColumn('user_code', function ($row) {
                    return '<code>' . e($row->user_code ?: ('AZ' . (1000 + $row->id))) . '</code>';
                })

                ->editColumn('status', function ($row) {
                    return match ($row->status) {
                        'active' => '<span class="badge bg-success">Active</span>',
                        'inactive' => '<span class="badge bg-danger">Inactive</span>',
                        default => '<span class="badge bg-secondary">Suspended</span>',
                    };
                })

                ->addColumn('action', function ($row) use ($rolePermissions) {
                    $editUrl = route('users.edit', $row->id);

                    $actionBtns = '<div class="admin-icon-actions">';

                    $actionBtns .= '<a href="javascript:void(0);" class="admin-icon-btn warning open-direct-notification-modal" data-user-id="' . $row->id . '" data-user-name="' . e($row->name) . '" title="Send Push Notification"><i class="bi bi-bell-fill"></i></a>';

                    if (in_array(16, $rolePermissions)) {
                        $actionBtns .= '<a href="' . $editUrl . '" class="admin-icon-btn primary" title="Edit User"><i class="bi bi-pencil-square"></i></a>';
                    }
                    if (in_array(17, $rolePermissions)) {
                        $actionBtns .= '<a href="javascript:void(0);" class="admin-icon-btn danger deleteUserBtn" data-id="' . $row->id . '" title="Delete User"><i class="bi bi-trash-fill"></i></a>';
                    }

                    $actionBtns .= '</div>';
                    return $actionBtns;
                })

                ->rawColumns(['profile_image', 'user_code', 'status', 'action'])
                ->make(true);
        }

        return view('admin.users.index');
    }


    public function create()
    {
        $cities = CityModel::all();
        return view('admin.users.create', compact('cities'));
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'dob' => 'required|date|before:today|after:1900-01-01',
                'city' => 'required|exists:cities,id',
                'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:users,phone'],
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'address' => 'required|string',
            ],
            [
                'dob.before' => 'Date of birth cannot be in the future.',
                'dob.after' => 'Date of birth is too far in the past.',
            ]
        );

        try {
            DB::beginTransaction();

            $filename = null;

            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $extension = $file->getClientOriginalExtension();
                $filename = time() . '_' . Str::random(10) . '.' . $extension;
                $file->move(public_path('uploads/profile_images/'), $filename);
            }


            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'country' => '1',
                'city_id' => $request->city,
                'dob' => $request->dob,
                'address' => $request->address,
                'profile_image' => $filename,
                'role' => '0', // 0 for user, 1 for provider
                'status' => 'active',
            ]);

            DB::commit();

            return redirect()->route('users.index')->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User creation failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to create user. Please try again.')->withInput();
        }
    }

    public function edit($id)
    {
        $user = User::where('id', $id)->where('role', '0')->firstOrFail();
        if (!$user) {
            return redirect()->route('users.index')->with('error', 'User not found.');
        }
        $cities = CityModel::all();

        return view('admin.users.edit', compact('user', 'cities'));
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'dob' => 'nullable|date',
            'city' => 'nullable|exists:cities,id',
            'phone' => ['required', 'unique:users,phone,' . $id],
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'address' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $user = User::where('id', $id)->where('role', '0')->firstOrFail();




            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $extension = $file->getClientOriginalExtension();
                $filename = time() . '_' . Str::random(10) . '.' . $extension;
                $file->move(public_path('uploads/profile_images/'), $filename);


                if (!empty($user->profile_image)) {
                    $oldPath = public_path('uploads/profile_images/' . $user->profile_image);
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }


                $user->profile_image = $filename;
            }

            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = preg_replace('/\s+/', '', $request->phone);
            $user->city_id = $request->city;
            $user->dob = $request->dob;
            $user->address = $request->address;
            $user->status = $request->status;
            $user->save();




            DB::commit();

            return redirect()->route('users.index')->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User update failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to update user. Please try again.')->withInput();
        }
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json([
            'status' => 200,
            'message' => 'User deleted successfully.'
        ]);
    }
}
